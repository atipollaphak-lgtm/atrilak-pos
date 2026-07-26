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

class MemoryStorage {
    constructor() {
        this.values = new Map();
    }

    getItem(key) {
        return this.values.get(key) ?? null;
    }

    setItem(key, value) {
        this.values.set(key, value);
    }

    removeItem(key) {
        this.values.delete(key);
    }
}

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

function baseContext(storage) {
    return vm.createContext({
        Array,
        Date,
        Error,
        Event: class Event {
            constructor(type) {
                this.type = type;
            }
        },
        FormData: null,
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
        sessionStorage: storage
    });
}

const cashPayment = {
    payment_method: "cash",
    cash_amount: "20.00",
    promptpay_amount: "0.00",
    received_amount: "20.00"
};

function paymentUiStub(calls) {
    return {
        createController(options) {
            calls.paymentOptions = options;

            return {
                open() {
                    calls.paymentOpens++;
                }
            };
        },
        payload(payment) {
            return { ...payment };
        }
    };
}

async function submitV1(harness) {
    harness.listeners.submit(event());

    return harness.calls.paymentOptions.onConfirm(cashPayment);
}

async function submitV2(harness) {
    harness.listeners.click();

    return harness.calls.paymentOptions.onConfirm(cashPayment);
}

function v1Harness(fetchImplementation, storage = new MemoryStorage()) {
    const listeners = {};
    const keyInput = { value: "" };
    const button = { disabled: false };
    const hidden = {
        "sale-payment-method": { value: "" },
        "sale-cash-amount": { value: "" },
        "sale-promptpay-amount": { value: "" },
        "sale-received-amount": { value: "" }
    };
    const form = {
        action: "/sales/store",
        dataset: { successUrl: "/sales" },
        fields: [
            ["_token", "csrf"],
            ["customer_id", "7"],
            ["sale_date", "2026-07-15"],
            ["product_id[]", "3"],
            ["qty[]", "2.00"],
            ["selling_price[]", "10.00"]
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
        alerts: 0,
        assigned: 0,
        fetches: 0,
        opened: 0,
        popupClosed: 0,
        popupReplaced: 0,
        paymentOpens: 0,
        paymentOptions: null,
        requestKeys: []
    };
    const popup = {
        closed: false,
        close() {
            this.closed = true;
            calls.popupClosed++;
        },
        location: {
            replace() {
                calls.popupReplaced++;
            }
        }
    };
    const context = baseContext(storage);
    context.FormData = FakeFormData;
    context.PosPayment = paymentUiStub(calls);
    context.document = {
        getElementById(id) {
            return {
                saleForm: form,
                "btn-submit-sale-v1": button,
                "sale-idempotency-key": keyInput,
                net_total: { value: "20.00" },
                ...hidden
            }[id] ?? null;
        }
    };
    context.window = {
        location: {
            assign() {
                calls.assigned++;
            }
        },
        open() {
            calls.opened++;
            return popup;
        }
    };
    context.alert = () => { calls.alerts++; };
    context.fetch = async (url, options) => {
        calls.fetches++;
        calls.requestKeys.push(options.body.get("idempotency_key"));
        return fetchImplementation(calls.fetches);
    };
    vm.runInContext(intentSource, context);
    vm.runInContext(v1Source, context);

    return { button, calls, form, listeners, storage };
}

function v2Harness(fetchImplementation, storage = new MemoryStorage()) {
    const listeners = {};
    const button = {
        disabled: false,
        addEventListener(type, listener) {
            listeners[type] = listener;
        }
    };
    const elements = {
        "btn-submit-sale": button,
        "cart-total": { textContent: "20.00" },
        "customer-id": { value: "7" },
        "delivery-address-id": { value: "11" },
        "technician-id": { value: "" },
        "sale-date": { value: "2026-07-15" },
        "is-pickup": {
            checked: false,
            dispatchEvent() {}
        },
        "pos-search-input": {
            value: "",
            focus() {}
        }
    };
    const calls = {
        alerts: 0,
        fetches: 0,
        navigations: [],
        paymentOpens: 0,
        paymentOptions: null,
        renders: 0,
        requestKeys: []
    };
    const context = baseContext(storage);
    context.PosPayment = paymentUiStub(calls);
    context.document = {
        getElementById(id) {
            return elements[id] ?? null;
        },
        querySelector() {
            return { getAttribute: () => "csrf" };
        }
    };
    context.window = {
        location: {
            assign(url) {
                calls.navigations.push(url);
            }
        }
    };
    context.alert = () => { calls.alerts++; };
    context.fetch = async (url, options) => {
        calls.fetches++;
        const body = JSON.parse(options.body);
        calls.requestKeys.push(body.idempotency_key);
        return fetchImplementation(calls.fetches);
    };
    context.renderCart = () => { calls.renders++; };
    vm.runInContext(`
        var cart = [{
            productId: 3,
            productUnitId: 9,
            qty: 2,
            price: 10
        }];
        var customerAddresses = [{ id: 11, delivery_zone_id: 4 }];
        var deliveryFee = 25;
        var discount = 5;
    `, context);
    vm.runInContext(intentSource, context);
    vm.runInContext(v2Source, context);
    vm.runInContext("bindSubmitSale()", context);

    return { button, calls, context, listeners, storage };
}

test("V1 double submit sends one request and handles confirmed success once", async () => {
    const harness = v1Harness(() => response(200, {
        success: true,
        invoice_url: "/sales/1/invoice"
    }));

    await Promise.all([
        submitV1(harness),
        submitV1(harness)
    ]);

    assert.equal(harness.calls.fetches, 1);
    assert.equal(harness.calls.opened, 1);
    assert.equal(harness.calls.popupReplaced, 1);
    assert.equal(harness.calls.assigned, 1);
    assert.equal(harness.storage.values.size, 0);
});

test("V1 network failure preserves pending key and same-page retry reuses it", async () => {
    const harness = v1Harness(call => {
        if (call === 1) {
            throw new Error("network timeout");
        }

        return response(200, { success: true, invoice_url: "/sales/1/invoice" });
    });

    await assert.rejects(submitV1(harness), /network timeout/);
    assert.equal(harness.storage.values.size, 1);
    assert.equal(harness.button.disabled, false);
    await submitV1(harness);

    assert.equal(harness.calls.fetches, 2);
    assert.equal(harness.calls.requestKeys[0], harness.calls.requestKeys[1]);
    assert.equal(harness.storage.values.size, 0);
});

test("V1 reload recovery reuses the pending key for unchanged form data", async () => {
    const storage = new MemoryStorage();
    const firstPage = v1Harness(() => {
        throw new Error("network timeout");
    }, storage);
    await assert.rejects(submitV1(firstPage), /network timeout/);
    const reloadedPage = v1Harness(() => response(200, {
        success: true,
        invoice_url: "/sales/1/invoice"
    }), storage);

    await submitV1(reloadedPage);

    assert.equal(firstPage.calls.requestKeys[0], reloadedPage.calls.requestKeys[0]);
    assert.equal(storage.values.size, 0);
});

test("V1 definitive validation error clears pending state and releases guard", async () => {
    const harness = v1Harness(() => response(422, {
        success: false,
        message: "invalid"
    }));

    await assert.rejects(submitV1(harness), /invalid/);

    assert.equal(harness.calls.fetches, 1);
    assert.equal(harness.calls.popupClosed, 1);
    assert.equal(harness.storage.values.size, 0);
    assert.equal(harness.button.disabled, false);
});

test("V2 double submit navigates to the invoice and resets cart once", async () => {
    const harness = v2Harness(() => response(200, {
        success: true,
        invoice_url: "/sales/1/invoice"
    }));

    await Promise.all([
        submitV2(harness),
        submitV2(harness)
    ]);

    assert.equal(harness.calls.fetches, 1);
    assert.deepEqual(harness.calls.navigations, ["/sales/1/invoice"]);
    assert.equal(harness.calls.renders, 1);
    assert.equal(vm.runInContext("cart.length", harness.context), 0);
    assert.equal(harness.storage.values.size, 0);
    assert.equal(harness.button.disabled, false);
});

test("V2 validation error preserves cart, clears key, and releases guard", async () => {
    const harness = v2Harness(() => response(422, {
        success: false,
        message: "invalid"
    }));

    await assert.rejects(submitV2(harness), /invalid/);

    assert.equal(vm.runInContext("cart.length", harness.context), 1);
    assert.equal(harness.calls.renders, 0);
    assert.equal(harness.storage.values.size, 0);
    assert.equal(harness.button.disabled, false);
});

test("V2 5xx unknown outcome preserves cart and pending key", async () => {
    const harness = v2Harness(() => response(500, {
        success: false,
        message: "server error"
    }));

    await assert.rejects(submitV2(harness), /server error/);

    assert.equal(vm.runInContext("cart.length", harness.context), 1);
    assert.equal(harness.calls.renders, 0);
    assert.equal(harness.storage.values.size, 1);
    assert.equal(harness.button.disabled, false);
});

test("V2 network timeout preserves cart and pending key", async () => {
    const harness = v2Harness(() => {
        throw new Error("network timeout");
    });

    await assert.rejects(submitV2(harness), /network timeout/);

    assert.equal(vm.runInContext("cart.length", harness.context), 1);
    assert.equal(harness.calls.renders, 0);
    assert.equal(harness.storage.values.size, 1);
    assert.equal(harness.button.disabled, false);
});

test("V2 reload recovery reuses the pending key for a reconstructed unchanged cart", async () => {
    const storage = new MemoryStorage();
    const firstPage = v2Harness(() => {
        throw new Error("network timeout");
    }, storage);
    await assert.rejects(submitV2(firstPage), /network timeout/);
    const reloadedPage = v2Harness(() => response(200, {
        success: true,
        invoice_url: "/sales/1/invoice"
    }), storage);

    await submitV2(reloadedPage);

    assert.equal(firstPage.calls.requestKeys[0], reloadedPage.calls.requestKeys[0]);
    assert.equal(storage.values.size, 0);
});
