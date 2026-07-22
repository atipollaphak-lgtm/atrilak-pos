import assert from "node:assert/strict";
import { webcrypto } from "node:crypto";
import { readFileSync } from "node:fs";
import test from "node:test";
import { TextEncoder } from "node:util";
import vm from "node:vm";

const intentSource = readFileSync(
    new URL("../../public/js/modules/sale-intent-storage.js", import.meta.url),
    "utf8"
);
const v1Source = readFileSync(
    new URL("../../public/js/modules/pos-v1-submit.js", import.meta.url),
    "utf8"
);
const v2Source = readFileSync(
    new URL("../../public/js/modules/pos-submit.js", import.meta.url),
    "utf8"
);
const paymentSource = readFileSync(
    new URL("../../public/js/modules/pos-payment.js", import.meta.url),
    "utf8"
);
const salesViewSource = readFileSync(
    new URL("../../resources/views/sales/index.blade.php", import.meta.url),
    "utf8"
);

const payments = {
    cash: {
        payment_method: "cash",
        cash_amount: "100.00",
        promptpay_amount: "0.00",
        received_amount: "150.00",
        change_amount: "50.00"
    },
    promptpay: {
        payment_method: "promptpay",
        cash_amount: "0.00",
        promptpay_amount: "100.00",
        received_amount: "0.00",
        change_amount: "0.00"
    },
    mixed: {
        payment_method: "mixed",
        cash_amount: "40.00",
        promptpay_amount: "60.00",
        received_amount: "50.00",
        change_amount: "10.00"
    }
};

function response(status, data) {
    return {
        ok: status >= 200 && status < 300,
        status,
        async text() {
            return JSON.stringify(data);
        }
    };
}

function event() {
    return {
        defaultPrevented: false,
        preventDefault() {
            this.defaultPrevented = true;
        }
    };
}

async function waitFor(predicate) {
    for (let attempt = 0; attempt < 20; attempt++) {
        if (predicate()) {
            return;
        }

        await new Promise(resolve => setTimeout(resolve, 0));
    }
}

function baseContext() {
    return vm.createContext({
        Array,
        Date,
        Error,
        Event: class Event {},
        JSON,
        Math,
        Number,
        Object,
        Set,
        String,
        TextEncoder,
        Uint8Array,
        alert() {},
        console,
        crypto: webcrypto,
        sessionStorage: {
            value: null,
            getItem() { return this.value; },
            setItem(key, value) { this.value = value; },
            removeItem() { this.value = null; }
        }
    });
}

function paymentUiStub(calls) {
    return {
        createController(options) {
            calls.paymentOptions = options;

            return {
                open() {
                    calls.modalOpens++;
                }
            };
        },
        payload(payment) {
            return {
                payment_method: payment.payment_method,
                cash_amount: payment.cash_amount,
                promptpay_amount: payment.promptpay_amount,
                received_amount: payment.received_amount
            };
        }
    };
}

function v1Harness(fetchImplementation) {
    const listeners = {};
    const hidden = {
        "sale-payment-method": { value: "" },
        "sale-cash-amount": { value: "" },
        "sale-promptpay-amount": { value: "" },
        "sale-received-amount": { value: "" }
    };
    const keyInput = { value: "" };
    const button = { disabled: false };
    const form = {
        action: "/sales/store",
        dataset: { successUrl: "/sales" },
        fields: [
            ["_token", "csrf"],
            ["sale_date", "2026-07-16"],
            ["product_id[]", "3"],
            ["qty[]", "1.00"],
            ["selling_price[]", "100.00"]
        ],
        addEventListener(type, listener) {
            listeners[type] = listener;
        },
        querySelector() {
            return { value: "csrf" };
        }
    };
    class FakeFormData {
        constructor() {
            this.values = form.fields.concat([
                ["idempotency_key", keyInput.value],
                ["payment_method", hidden["sale-payment-method"].value],
                ["cash_amount", hidden["sale-cash-amount"].value],
                ["promptpay_amount", hidden["sale-promptpay-amount"].value],
                ["received_amount", hidden["sale-received-amount"].value]
            ]);
        }

        entries() {
            return this.values[Symbol.iterator]();
        }

        get(name) {
            return this.values.find(entry => entry[0] === name)?.[1] ?? null;
        }
    }
    const calls = {
        fetches: 0,
        modalOpens: 0,
        paymentOptions: null,
        request: null
    };
    const context = baseContext();
    context.FormData = FakeFormData;
    context.PosPayment = paymentUiStub(calls);
    context.document = {
        getElementById(id) {
            return {
                saleForm: form,
                "btn-submit-sale-v1": button,
                "sale-idempotency-key": keyInput,
                net_total: { value: "100.00" },
                ...hidden
            }[id] ?? null;
        }
    };
    context.window = {
        location: { assign() {} },
        open() {
            return {
                closed: false,
                close() {},
                location: { replace() {} }
            };
        }
    };
    context.fetch = async (url, options) => {
        calls.fetches++;
        calls.request = options.body;

        return fetchImplementation(calls.fetches);
    };
    vm.runInContext(intentSource, context);
    vm.runInContext(v1Source, context);

    return { calls, listeners };
}

function v2Harness(fetchImplementation) {
    const listeners = {};
    const button = {
        disabled: false,
        addEventListener(type, listener) {
            listeners[type] = listener;
        }
    };
    const calls = {
        fetches: 0,
        modalOpens: 0,
        paymentOptions: null,
        request: null
    };
    const context = baseContext();
    context.PosPayment = paymentUiStub(calls);
    context.document = {
        getElementById(id) {
            return {
                "btn-submit-sale": button,
                "cart-total": { textContent: "100.00" },
                "customer-id": { value: "" },
                "delivery-address-id": { value: "" },
                "technician-id": { value: "" },
                "sale-date": { value: "2026-07-16" },
                "is-pickup": { checked: true, dispatchEvent() {} },
                "pos-search-input": { value: "", focus() {} }
            }[id] ?? null;
        },
        querySelector() {
            return { getAttribute: () => "csrf" };
        }
    };
    context.window = { open() {} };
    context.renderCart = () => {};
    context.fetch = async (url, options) => {
        calls.fetches++;
        calls.request = JSON.parse(options.body);

        return fetchImplementation(calls.fetches);
    };
    vm.runInContext(`
        var cart = [{ productId: 3, productUnitId: 9, qty: 1, price: 100 }];
        var customerAddresses = [];
        var deliveryFee = 0;
        var discount = 0;
    `, context);
    vm.runInContext(intentSource, context);
    vm.runInContext(v2Source, context);
    vm.runInContext("bindSubmitSale()", context);

    return { calls, context, listeners };
}

function extractFunction(source, name) {
    const start = source.indexOf(`function ${name}()`);

    assert.notEqual(start, -1, `${name} must exist in the POS view`);

    const bodyStart = source.indexOf("{", start);
    let depth = 0;

    for (let index = bodyStart; index < source.length; index++) {
        if (source[index] === "{") {
            depth++;
        } else if (source[index] === "}") {
            depth--;

            if (depth === 0) {
                return source.slice(start, index + 1);
            }
        }
    }

    throw new Error(`Unable to extract ${name}`);
}

class PaymentElement {
    constructor({ value = "" } = {}) {
        this.value = value;
        this.textContent = "";
        this.innerText = "";
        this.disabled = false;
        this.listeners = {};
        this.classList = {
            add() {},
            remove() {}
        };
    }

    addEventListener(type, listener) {
        this.listeners[type] = listener;
    }

    focus() {}

    select() {}
}

function v1TotalHarnessWithoutProfitElements() {
    const elements = {
        "grand-total": new PaymentElement(),
        "total-cost": new PaymentElement(),
        "profit-percent": new PaymentElement(),
        delivery_fee: new PaymentElement({ value: "0" }),
        discount: new PaymentElement({ value: "0" }),
        net_total: new PaymentElement({ value: "0.00" }),
        paymentModal: new PaymentElement(),
        "payment-total": new PaymentElement(),
        "payment-method": new PaymentElement({ value: "cash" }),
        "payment-cash-summary": new PaymentElement(),
        "payment-cash-amount": new PaymentElement(),
        "payment-mixed-cash-group": new PaymentElement(),
        "payment-mixed-cash": new PaymentElement(),
        "payment-promptpay-group": new PaymentElement(),
        "payment-promptpay-amount": new PaymentElement(),
        "payment-received-group": new PaymentElement(),
        "payment-received": new PaymentElement(),
        "payment-change": new PaymentElement(),
        "payment-error": new PaymentElement(),
        "btn-confirm-payment": new PaymentElement(),
        "btn-cancel-payment": new PaymentElement()
    };
    const qty = new PaymentElement({ value: "1" });
    const price = new PaymentElement({ value: "15.00" });
    price.dataset = {};
    const select = {
        selectedIndex: 0,
        options: [{ dataset: { cost: "10.00", tiers: "[]" } }]
    };
    const lineTotal = new PaymentElement();
    const row = {
        querySelector(selector) {
            return {
                ".qty": qty,
                ".price": price,
                ".product-select": select,
                ".line-total": lineTotal
            }[selector] ?? null;
        }
    };
    const document = {
        getElementById(id) {
            return elements[id] ?? null;
        },
        querySelectorAll(selector) {
            return selector === "#sale-items tr" ? [row] : [];
        }
    };
    const context = baseContext();
    context.document = document;
    context.window = {};
    context.$ = () => ({
        modal() {},
        off() { return this; },
        on() { return this; }
    });

    vm.runInContext(extractFunction(salesViewSource, "calculateTotals"), context);
    vm.runInContext(paymentSource, context);

    return {
        calculate() {
            vm.runInContext("calculateTotals()", context);
        },
        openPayment() {
            const controller = context.window.PosPayment.createController({
                getTotal: () => elements.net_total.value,
                onConfirm: async () => {}
            });

            controller.open();
        },
        elements
    };
}

test("V1 POS calculates net and payment totals without profit markup", () => {
    const harness = v1TotalHarnessWithoutProfitElements();

    assert.doesNotThrow(() => harness.calculate());
    assert.equal(harness.elements["grand-total"].innerText, "15.00");
    assert.equal(harness.elements.net_total.value, "15.00");

    harness.openPayment();

    assert.equal(harness.elements["payment-total"].textContent, "15.00");
});

for (const [version, harnessFactory, trigger] of [
    ["V1", v1Harness, harness => harness.listeners.submit(event())],
    ["V2", v2Harness, harness => harness.listeners.click()]
]) {
    for (const method of ["cash", "promptpay", "mixed"]) {
        test(`${version} submits canonical ${method} fields without browser change`, async () => {
            const harness = harnessFactory(() => response(200, {
                success: true,
                invoice_url: "/sales/1/invoice"
            }));

            await trigger(harness);
            assert.equal(harness.calls.fetches, 0);
            assert.equal(harness.calls.modalOpens, 1);
            await harness.calls.paymentOptions.onConfirm(payments[method]);

            const request = version === "V1"
                ? Object.fromEntries([
                    "payment_method",
                    "cash_amount",
                    "promptpay_amount",
                    "received_amount",
                    "change_amount"
                ].map(field => [field, harness.calls.request.get(field)]))
                : harness.calls.request;

            assert.equal(request.payment_method, payments[method].payment_method);
            assert.equal(request.cash_amount, payments[method].cash_amount);
            assert.equal(request.promptpay_amount, payments[method].promptpay_amount);
            assert.equal(request.received_amount, payments[method].received_amount);
            assert.equal(request.change_amount ?? undefined, undefined);
        });
    }
}

test("V2 repeated payment confirmation starts only one active request", async () => {
    let finish;
    const pending = new Promise(resolve => { finish = resolve; });
    const harness = v2Harness(() => pending);

    harness.listeners.click();
    await Promise.resolve();
    assert.notEqual(harness.calls.paymentOptions, null);
    const first = harness.calls.paymentOptions.onConfirm(payments.cash);
    const second = harness.calls.paymentOptions.onConfirm(payments.cash);

    await waitFor(() => harness.calls.fetches === 1);
    assert.equal(harness.calls.fetches, 1);
    finish(response(200, { success: true, invoice_url: "/sales/1/invoice" }));
    await Promise.all([first, second]);
    assert.equal(harness.calls.fetches, 1);
});

test("V2 backend validation error preserves cart and allows payment retry", async () => {
    const harness = v2Harness(call => call === 1
        ? response(422, { success: false, message: "แก้ไขข้อมูลการชำระเงิน" })
        : response(200, { success: true, invoice_url: "/sales/1/invoice" }));

    await harness.listeners.click();
    await assert.rejects(
        harness.calls.paymentOptions.onConfirm(payments.cash),
        /แก้ไขข้อมูลการชำระเงิน/
    );
    assert.equal(vm.runInContext("cart.length", harness.context), 1);

    await harness.calls.paymentOptions.onConfirm(payments.cash);
    assert.equal(harness.calls.fetches, 2);
});
