import assert from "node:assert/strict";
import fs from "node:fs";
import test from "node:test";
import vm from "node:vm";

const source = fs.readFileSync(
    new URL("../../public/js/modules/final-pos.js", import.meta.url),
    "utf8",
);
const saleV3Source = fs.readFileSync(
    new URL("../../public/js/modules/sale-v3.js", import.meta.url),
    "utf8",
);

class FakeElement {
    constructor({ dataset = {}, value = "", checked = false } = {}) {
        this.dataset = dataset;
        this.value = value;
        this.checked = checked;
        this.disabled = false;
        this.innerHTML = "";
        this.textContent = "";
        this.selectedOptions = [];
        this.listeners = new Map();
        const classes = new Set();
        this.classList = {
            add(...names) { names.forEach((name) => classes.add(name)); },
            remove(...names) { names.forEach((name) => classes.delete(name)); },
            toggle(name, force) {
                if (force === true) classes.add(name);
                else if (force === false) classes.delete(name);
                else if (classes.has(name)) classes.delete(name);
                else classes.add(name);
            },
            contains(name) { return classes.has(name); },
        };
    }

    addEventListener(type, listener) {
        const listeners = this.listeners.get(type) || [];
        listeners.push(listener);
        this.listeners.set(type, listeners);
    }

    async dispatch(type) {
        for (const listener of this.listeners.get(type) || []) {
            await listener({ target: this });
        }
    }

    dispatchEvent(event) {
        return this.dispatch(event.type);
    }

    querySelectorAll(selector) {
        if (selector === "[data-resume-hold]") {
            return this.resumeButton ? [this.resumeButton] : [];
        }
        if (selector === "[data-delete-hold]") {
            return [];
        }
        return [];
    }
}

function response(status, data) {
    return {
        ok: status >= 200 && status < 300,
        status,
        async json() { return data; },
    };
}

function createHarness(hold) {
    const holdsButton = new FakeElement();
    const resumeButton = new FakeElement({ dataset: { resumeHold: String(hold.id) } });
    const holdList = new FakeElement();
    holdList.resumeButton = resumeButton;
    const customerSelect = new FakeElement();
    const addressSelect = new FakeElement();
    const deliveryDate = new FakeElement({ value: "2026-07-30" });
    const pickup = new FakeElement({ checked: false });
    const discount = new FakeElement({ value: "0.00" });
    const elements = new Map([
        ["#v3-hold-list", holdList],
        ["#v3-delivery-date", deliveryDate],
        ["#v3-pickup", pickup],
        ["#v3-discount", discount],
        ["#v3-customer-name", new FakeElement()],
        ["#v3-customer-phone", new FakeElement()],
        ["#final-payment-status", new FakeElement()],
        ["#final-payment-title", new FakeElement()],
        ["#final-payment-subtitle", new FakeElement()],
        ["#final-print-delivery", new FakeElement()],
        ["#final-print-tax", new FakeElement()],
        ["#final-tax-help", new FakeElement()],
        ["#final-payment-close", new FakeElement()],
        ["#final-payment-method-summary", new FakeElement()],
        ["#final-payment-method-label", new FakeElement()],
        ["#final-payment-amounts", new FakeElement()],
    ]);
    const requests = [];
    const alerts = [];
    const openedUrls = [];
    let addressLoads = 0;
    const state = {
        cart: [{ key: "old", name: "old" }],
        address: null,
        zone: null,
        deliveryFee: 0,
        deliveryFeeEdited: false,
        discount: 0,
        note: "",
        deliveryType: "pickup",
        addresses: [],
        draftZone: null,
    };

    addressSelect.addEventListener("change", () => {
        state.address = addressSelect.selectedOptions[0] || null;
    });

    const document = {
        querySelector(selector) {
            if (!elements.has(selector)) elements.set(selector, new FakeElement());
            return elements.get(selector);
        },
        querySelectorAll(selector) {
            if (selector === '[data-final-action="holds"]') return [holdsButton];
            return [];
        },
    };
    const window = {
        document,
        Event: class Event {
            constructor(type) { this.type = type; }
        },
        alert(message) { alerts.push(message); },
        confirm() { return true; },
        setTimeout(callback) { callback(); return 1; },
        jQuery() { return { modal() {} }; },
        open(url) { openedUrls.push(url); return {}; },
        async fetch(url, options = {}) {
            requests.push({ url, method: options.method || "GET" });
            if (url === "/holds" && options.method === "POST") {
                return response(201, {
                    success: true,
                    hold_bill: { id: 99, hold_no: "HLD-TEST-0099" },
                });
            }
            if (url === "/holds") {
                return response(200, { success: true, data: [hold] });
            }
            if (url === `/holds/${hold.id}` && !options.method) {
                return response(200, { success: true, data: hold });
            }
            if (url === `/holds/${hold.id}` && options.method === "DELETE") {
                return response(200, { success: true });
            }
            throw new Error(`Unexpected request ${options.method || "GET"} ${url}`);
        },
    };
    window.window = window;
    window.globalThis = window;

    vm.runInNewContext(source, window);
    window.FinalPos.configure({
        state,
        root: {
            dataset: {
                holdListUrl: "/holds",
                holdStoreUrl: "/holds",
                holdUrlTemplate: "/holds/__HOLD__",
                documentUrlTemplate: "/sales/__SALE__/invoice-v2",
                saleDate: "2026-07-30",
            },
        },
        customerSelect,
        addressSelect,
        async loadAddresses() {
            addressLoads += 1;
            addressSelect.selectedOptions = [{
                value: String(hold.customer_delivery_address_id),
                textContent: "TEST address",
            }];
        },
        render() {},
        total() { return "0.00"; },
        payment: { open() {} },
    });

    return {
        addressSelect,
        alerts,
        customerSelect,
        elements,
        finalPos: window.FinalPos,
        get addressLoads() { return addressLoads; },
        holdsButton,
        openedUrls,
        pickup,
        requests,
        resumeButton,
        deliveryDate,
        state,
    };
}

async function openAndResume(harness) {
    const previousCart = harness.state.cart;
    harness.state.cart = [];
    await harness.holdsButton.dispatch("click");
    await new Promise((resolve) => setImmediate(resolve));
    harness.state.cart = previousCart;
    await harness.resumeButton.dispatch("click");
}

test("resume restores the complete hold context without consuming it before payment", async () => {
    const hold = {
        id: 7,
        customer_id: 12,
        customer_delivery_address_id: 34,
        sale_date: "2026-07-29",
        delivery_type: "pickup",
        discount: "10.00",
        delivery_fee: "0.00",
        notes: "TEST hold note",
        delivery_zone_id: 9,
        delivery_zone_name_snapshot: "TEST zone",
        delivery_zone_markup_percent_snapshot: "5.00",
        delivery_zone_rounding_increment_snapshot: "0.25",
        delivery_zone_minimum_profit_snapshot: "100.00",
        customer: { name: "TEST customer" },
        items: [{
            product_id: 1,
            product_unit_id: 2,
            qty: "3.00",
            selling_price: "25.00",
            product_name_snapshot: "TEST product",
            unit_name_snapshot: "piece",
            product: { id: 1, unit: "piece" },
            product_unit: { id: 2, unit: { name: "piece" } },
        }],
    };
    const harness = createHarness(hold);

    await openAndResume(harness);

    assert.equal(harness.addressLoads, 1);
    assert.equal(harness.customerSelect.value, "12");
    assert.equal(harness.addressSelect.value, "34");
    assert.equal(harness.deliveryDate.value, "2026-07-30");
    assert.equal(harness.pickup.checked, true);
    assert.equal(harness.state.note, "TEST hold note");
    assert.equal(harness.state.discount, 10);
    assert.equal(harness.state.deliveryFee, 0);
    assert.equal(harness.state.zone.name, "TEST zone");
    assert.equal(harness.state.cart.length, 1);
    assert.equal(harness.state.cart[0].qty, 3);
    assert.equal(harness.state.holdBillId, 7);
    assert.equal(
        harness.requests.some((request) => request.method === "DELETE"),
        false,
    );
});

test("resume restores held price override metadata without repricing", async () => {
    const hold = {
        id: 13,
        customer_id: null,
        customer_delivery_address_id: null,
        sale_date: "2026-07-29",
        delivery_type: "pickup",
        discount: "0.00",
        delivery_fee: "0.00",
        items: [{
            product_id: 1,
            product_unit_id: 2,
            qty: "1.00",
            selling_price: "99.50",
            original_price: "100.00",
            price_override_flag: true,
            product_name_snapshot: "TEST held override",
            unit_name_snapshot: "piece",
            product: { id: 1, unit: "piece" },
            product_unit: { id: 2, unit: { name: "piece" } },
        }],
    };
    const harness = createHarness(hold);

    await openAndResume(harness);

    assert.equal(harness.state.cart[0].price, 99.5);
    assert.equal(harness.state.cart[0].originalPrice, 100);
    assert.equal(harness.state.cart[0].priceWasEdited, true);
    assert.equal(harness.state.cart[0].priceChangedSinceHold, false);
});

test("resume blocks the whole hold when a product no longer exists", async () => {
    const hold = {
        id: 8,
        customer_id: null,
        customer_delivery_address_id: null,
        sale_date: "2026-07-29",
        delivery_type: "delivery",
        discount: "0.00",
        delivery_fee: "50.00",
        items: [{
            product_id: null,
            product_unit_id: null,
            qty: "1.00",
            selling_price: "25.00",
            product_name_snapshot: "TEST deleted product",
            product: null,
            product_unit: null,
        }],
    };
    const harness = createHarness(hold);

    await openAndResume(harness);

    assert.equal(harness.state.cart[0].name, "old");
    assert.equal(harness.addressLoads, 0);
    assert.equal(
        harness.requests.some((request) => request.method === "DELETE"),
        false,
    );
    assert.equal(harness.alerts.length, 1);
});

test("resume blocks the whole hold when its selected product unit no longer exists", async () => {
    const hold = {
        id: 11,
        customer_id: null,
        customer_delivery_address_id: null,
        sale_date: "2026-07-29",
        delivery_type: "pickup",
        discount: "0.00",
        delivery_fee: "0.00",
        items: [{
            product_id: 1,
            product_unit_id: null,
            product_unit_id_snapshot: 99,
            qty: "1.00",
            selling_price: "25.00",
            product_name_snapshot: "TEST product",
            product: { id: 1, unit: "piece" },
            product_unit: null,
        }],
    };
    const harness = createHarness(hold);

    await openAndResume(harness);

    assert.equal(harness.state.cart[0].name, "old");
    assert.equal(harness.state.holdBillId, undefined);
    assert.equal(harness.alerts.length, 1);
});

test("starting a new bill clears a resumed hold reference", () => {
    assert.match(
        saleV3Source,
        /v3-new-bill[\s\S]*?state\.deliveryFee\s*=\s*0[\s\S]*?state\.deliveryFeeEdited\s*=\s*false[\s\S]*?state\.holdBillId\s*=\s*null[\s\S]*?render\(\)/,
    );
    assert.doesNotMatch(
        source,
        /v3-clear-cart['"]\)\?*\.addEventListener/,
    );
});

test("rapid repeated hold clicks create only one persistent hold", async () => {
    const harness = createHarness({
        id: 12,
        customer_id: null,
        customer_delivery_address_id: null,
        items: [],
    });

    await Promise.all([
        harness.holdsButton.dispatch("click"),
        harness.holdsButton.dispatch("click"),
    ]);

    assert.equal(
        harness.requests.filter((request) => request.method === "POST").length,
        1,
    );
});

test("finish resets the next bill and confirmation restores its actions", async () => {
    const harness = createHarness({
        id: 9,
        customer_id: null,
        customer_delivery_address_id: null,
        items: [],
    });
    harness.state.discount = 25;
    harness.state.deliveryFee = 50;
    harness.state.deliveryFeeEdited = true;
    harness.state.note = "TEST previous note";
    harness.customerSelect.value = "12";
    harness.pickup.checked = true;

    harness.finalPos.showSuccess({ sale_id: 90, sale_no: "SAL-TEST-90" });
    assert.equal(
        harness.elements.get("#final-payment-close").classList.contains("d-none"),
        true,
    );
    await harness.elements.get("#final-finish-payment").dispatch("click");

    assert.equal(harness.state.cart.length, 0);
    assert.equal(harness.state.discount, 0);
    assert.equal(harness.state.deliveryFee, 0);
    assert.equal(harness.state.deliveryFeeEdited, false);
    assert.equal(harness.state.note, "");
    assert.equal(harness.customerSelect.value, "");
    assert.equal(harness.pickup.checked, true);

    harness.elements.get("#final-confirm-payment").classList.add("d-none");
    harness.elements.get("#final-edit-items").classList.add("d-none");
    harness.elements.get("#final-document-panel").classList.remove("d-none");
    harness.elements.get("#final-print-delivery").disabled = false;
    harness.elements.get("#final-print-tax").disabled = false;
    harness.elements.get("#final-finish-payment").disabled = false;
    harness.state.cart = [{
        name: "TEST next product",
        unitName: "piece",
        qty: 1,
        price: 10,
    }];
    harness.finalPos.openConfirmation();

    assert.equal(
        harness.elements.get("#final-confirm-payment").classList.contains("d-none"),
        false,
    );
    assert.equal(
        harness.elements.get("#final-edit-items").classList.contains("d-none"),
        false,
    );
    assert.equal(
        harness.elements.get("#final-document-panel").classList.contains("d-none"),
        true,
    );
    assert.equal(harness.elements.get("#final-print-delivery").disabled, true);
    assert.equal(harness.elements.get("#final-print-tax").disabled, true);
    assert.equal(harness.elements.get("#final-finish-payment").disabled, true);
    assert.equal(
        harness.elements.get("#final-payment-close").classList.contains("d-none"),
        false,
    );
});

test("success printing uses the created sale id and configured document route", async () => {
    const harness = createHarness({
        id: 10,
        customer_id: null,
        customer_delivery_address_id: null,
        items: [],
    });
    harness.finalPos.showSuccess({ sale_id: 55, sale_no: "SAL-TEST-55" });
    await harness.elements.get("#final-print-delivery").dispatch("click");

    assert.deepEqual(
        harness.openedUrls,
        ["/sales/55/invoice-v2?document_type=delivery-note"],
    );
});

test("delivery success uses the delivery copy without changing the payment snapshot", () => {
    const harness = createHarness({
        id: 11,
        customer_id: 12,
        customer_delivery_address_id: 34,
        items: [],
    });
    harness.state.deliveryType = "delivery";
    harness.customerSelect.selectedOptions = [{
        dataset: {
            name: "TEST delivery customer",
            phone: "0800000000",
            taxNumber: "0100000000001",
            customerAddress: "TEST invoice address",
            branchType: "สำนักงานใหญ่",
        },
    }];
    harness.state.cart = [{ name: "TEST product", unitName: "piece", qty: 1, price: 100 }];
    harness.finalPos.openConfirmation();
    assert.match(harness.elements.get("#final-confirm-payment").innerHTML, /ยืนยันการจัดส่ง/);

    harness.finalPos.showSuccess({
        sale_id: 56,
        sale_no: "SAL-TEST-56",
        payment: {
            payment_method: "cash",
            cash_amount: "100.00",
            promptpay_amount: "0.00",
            received_amount: "120.00",
            change_amount: "20.00",
        },
    });

    assert.equal(harness.elements.get("#final-payment-status").textContent, "ยืนยันการจัดส่ง");
    assert.equal(harness.elements.get("#final-payment-title").textContent, "ยืนยันการจัดส่ง");
    assert.equal(harness.elements.get("#final-payment-subtitle").textContent, "ยืนยันการจัดส่ง");
    assert.equal(harness.elements.get("#final-payment-method-label").textContent, "วิธีชำระเงิน: เงินสด");
    assert.equal(harness.elements.get("#final-print-delivery").disabled, false);
    assert.equal(harness.elements.get("#final-print-tax").disabled, false);
    assert.doesNotMatch(source, /ยังไม่ชำระ/);
});

test("tax invoice printing stays disabled when customer tax data is incomplete", () => {
    const harness = createHarness({
        id: 12,
        customer_id: 13,
        customer_delivery_address_id: 35,
        items: [],
    });
    harness.state.deliveryType = "delivery";
    harness.customerSelect.selectedOptions = [{
        dataset: {
            name: "TEST incomplete tax customer",
            taxNumber: "0100000000001",
            customerAddress: "",
            branchType: "สาขา",
            branchNumber: "",
        },
    }];

    harness.finalPos.showSuccess({ sale_id: 57, sale_no: "SAL-TEST-57" });

    assert.equal(harness.elements.get("#final-print-tax").disabled, true);
    assert.match(harness.elements.get("#final-tax-help").textContent, /ที่อยู่ใบกำกับภาษี/);
    assert.match(harness.elements.get("#final-tax-help").textContent, /เลขสาขา/);
});

test("tax invoice printing stays disabled when only the delivery address is present", () => {
    const harness = createHarness({
        id: 13,
        customer_id: 14,
        customer_delivery_address_id: 36,
        items: [],
    });
    harness.state.deliveryType = "delivery";
    harness.state.address = { address: "TEST delivery-only address" };
    harness.customerSelect.selectedOptions = [{
        dataset: {
            name: "TEST customer with delivery address only",
            taxNumber: "0100000000001",
            customerAddress: "",
            branchType: "à¸ªà¸³à¸™à¸±à¸à¸‡à¸²à¸™à¹ƒà¸«à¸à¹ˆ",
        },
    }];

    harness.finalPos.showSuccess({ sale_id: 58, sale_no: "SAL-TEST-58" });

    assert.equal(harness.elements.get("#final-print-tax").disabled, true);
    assert.notEqual(harness.elements.get("#final-tax-help").textContent, "");
});

test("successful document popup prevents duplicate printing", async () => {
    const harness = createHarness({
        id: 14,
        customer_id: null,
        customer_delivery_address_id: null,
        items: [],
    });

    harness.finalPos.showSuccess({ sale_id: 59, sale_no: "SAL-TEST-59" });
    await harness.elements.get("#final-print-delivery").dispatch("click");
    await harness.elements.get("#final-print-delivery").dispatch("click");

    assert.deepEqual(
        harness.openedUrls,
        ["/sales/59/invoice-v2?document_type=delivery-note"],
    );
    assert.equal(harness.elements.get("#final-print-delivery").disabled, true);
});

test("success modal uses the server payment snapshot for cash, PromptPay, and mixed payments", () => {
    const expected = {
        cash: {
            label: "วิธีชำระเงิน: เงินสด",
            amounts: "เงินสด 100.00 · พร้อมเพย์ 0.00 · รับเงิน 150.00 · เงินทอน 50.00",
        },
        promptpay: {
            label: "วิธีชำระเงิน: พร้อมเพย์",
            amounts: "เงินสด 0.00 · พร้อมเพย์ 100.00 · รับเงิน 0.00 · เงินทอน 0.00",
        },
        mixed: {
            label: "วิธีชำระเงิน: เงินสด + พร้อมเพย์",
            amounts: "เงินสด 40.00 · พร้อมเพย์ 60.00 · รับเงิน 50.00 · เงินทอน 10.00",
        },
    };

    for (const [method, assertion] of Object.entries(expected)) {
        const harness = createHarness({
            id: 20,
            customer_id: null,
            customer_delivery_address_id: null,
            items: [],
        });

        harness.finalPos.showSuccess({
            sale_id: 120,
            sale_no: "SAL-TEST-120",
            payment: {
                payment_method: method,
                cash_amount: method === "cash" ? "100.00" : method === "mixed" ? "40.00" : "0.00",
                promptpay_amount: method === "promptpay" ? "100.00" : method === "mixed" ? "60.00" : "0.00",
                received_amount: method === "cash" ? "150.00" : method === "mixed" ? "50.00" : "0.00",
                change_amount: method === "cash" ? "50.00" : method === "mixed" ? "10.00" : "0.00",
            },
        });

        assert.equal(
            harness.elements.get("#final-payment-method-label").textContent,
            assertion.label,
        );
        assert.equal(
            harness.elements.get("#final-payment-amounts").textContent,
            assertion.amounts,
        );
    }
});

test("starting the next bill clears the previous payment snapshot", async () => {
    const harness = createHarness({
        id: 21,
        customer_id: null,
        customer_delivery_address_id: null,
        items: [],
    });

    harness.finalPos.showSuccess({
        sale_id: 121,
        sale_no: "SAL-TEST-121",
        payment: {
            payment_method: "promptpay",
            cash_amount: "0.00",
            promptpay_amount: "100.00",
            received_amount: "0.00",
            change_amount: "0.00",
        },
    });
    await harness.elements.get("#final-finish-payment").dispatch("click");

    assert.equal(
        harness.elements.get("#final-payment-method-label").textContent,
        "วิธีชำระเงิน: ยังไม่ได้ยืนยัน",
    );
    assert.equal(harness.elements.get("#final-payment-amounts").textContent, "");
});
