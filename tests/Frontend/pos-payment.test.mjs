import assert from "node:assert/strict";
import { existsSync, readFileSync } from "node:fs";
import test from "node:test";
import vm from "node:vm";

const paymentUrl = new URL(
    "../../public/js/modules/pos-payment.js",
    import.meta.url
);
const paymentSource = existsSync(paymentUrl)
    ? readFileSync(paymentUrl, "utf8")
    : "";

function paymentApi() {
    const context = vm.createContext({
        Error,
        Object,
        String,
        window: {}
    });

    vm.runInContext(paymentSource, context);

    return context.window.PosPayment;
}

test("shared POS payment module exists", () => {
    assert.equal(existsSync(paymentUrl), true);
});

test("cash payment payload uses total and calculates change in cents", () => {
    const payment = paymentApi().resolve("cash", "850.00", "", "1,000.00");

    assert.deepEqual(JSON.parse(JSON.stringify(payment)), {
        payment_method: "cash",
        cash_amount: "850.00",
        promptpay_amount: "0.00",
        received_amount: "1000.00",
        change_amount: "150.00"
    });
});

test("promptpay payment uses the full total without cash", () => {
    const payment = paymentApi().resolve("promptpay", "850.00", "", "");

    assert.deepEqual(JSON.parse(JSON.stringify(payment)), {
        payment_method: "promptpay",
        cash_amount: "0.00",
        promptpay_amount: "850.00",
        received_amount: "0.00",
        change_amount: "0.00"
    });
});

test("mixed payment derives promptpay and cash change without float drift", () => {
    const payment = paymentApi().resolve("mixed", "850.00", "300.00", "500.00");

    assert.deepEqual(JSON.parse(JSON.stringify(payment)), {
        payment_method: "mixed",
        cash_amount: "300.00",
        promptpay_amount: "550.00",
        received_amount: "500.00",
        change_amount: "200.00"
    });
});

test("payment payload excludes browser-calculated change", () => {
    const payment = paymentApi().resolve("cash", "100.00", "", "150.00");

    assert.deepEqual(JSON.parse(JSON.stringify(paymentApi().payload(payment))), {
        payment_method: "cash",
        cash_amount: "100.00",
        promptpay_amount: "0.00",
        received_amount: "150.00"
    });
});

for (const scenario of [
    ["blank received cash", "cash", "100.00", "", ""],
    ["received cash below allocation", "cash", "100.00", "", "99.99"],
    ["mixed cash is zero", "mixed", "100.00", "0.00", "100.00"],
    ["mixed cash equals total", "mixed", "100.00", "100.00", "100.00"],
    ["mixed zero total", "mixed", "0.00", "0.00", "0.00"],
    ["negative input", "cash", "100.00", "", "-1.00"],
    ["scale over two", "cash", "100.00", "", "100.001"],
    ["unknown method", "card", "100.00", "", "100.00"]
]) {
    test(`rejects ${scenario[0]}`, () => {
        assert.throws(
            () => paymentApi().resolve(...scenario.slice(1)),
            Error
        );
    });
}

test("zero-total cash and promptpay remain valid", () => {
    assert.equal(
        paymentApi().resolve("cash", "0.00", "", "0.00").cash_amount,
        "0.00"
    );
    assert.equal(
        paymentApi().resolve("promptpay", "0.00", "", "").promptpay_amount,
        "0.00"
    );
});

class FakeClassList {
    constructor(classes = []) {
        this.classes = new Set(classes);
    }

    add(name) {
        this.classes.add(name);
    }

    remove(name) {
        this.classes.delete(name);
    }

    contains(name) {
        return this.classes.has(name);
    }
}

class FakeElement {
    constructor({ classes = [], value = "" } = {}) {
        this.classList = new FakeClassList(classes);
        this.disabled = false;
        this.listeners = {};
        this.textContent = "";
        this.value = value;
    }

    addEventListener(type, listener) {
        this.listeners[type] = listener;
    }

    dispatch(type, event = {}) {
        return this.listeners[type]?.({
            key: undefined,
            preventDefault() {},
            ...event
        });
    }

    focus() {}

    select() {}
}

function controllerHarness(onConfirm = async () => {}, getInitialPayment = null) {
    let total = "100.00";
    const elements = {
        paymentModal: new FakeElement(),
        "payment-total": new FakeElement(),
        "payment-method": new FakeElement({ value: "cash" }),
        "payment-cash-summary": new FakeElement(),
        "payment-cash-amount": new FakeElement(),
        "payment-mixed-cash-group": new FakeElement({ classes: ["d-none"] }),
        "payment-mixed-cash": new FakeElement(),
        "payment-promptpay-group": new FakeElement({ classes: ["d-none"] }),
        "payment-promptpay-amount": new FakeElement(),
        "payment-received-group": new FakeElement(),
        "payment-received": new FakeElement(),
        "payment-change": new FakeElement(),
        "payment-error": new FakeElement({ classes: ["d-none"] }),
        "btn-confirm-payment": new FakeElement(),
        "btn-cancel-payment": new FakeElement()
    };
    const modalActions = [];
    const context = vm.createContext({
        BigInt,
        Error,
        Object,
        String,
        document: {
            getElementById(id) {
                return elements[id] ?? null;
            }
        },
        window: {}
    });
    context.$ = () => ({
        modal(action) {
            modalActions.push(action);
        },
        off() {
            return this;
        },
        on() {
            return this;
        }
    });
    vm.runInContext(paymentSource, context);
    const controller = context.window.PosPayment.createController({
        getTotal: () => total,
        getInitialPayment,
        onConfirm
    });

    return {
        controller,
        elements,
        modalActions,
        setTotal(value) {
            total = value;
        }
    };
}

test("payment modal opens with fresh cash defaults and cancel never confirms", () => {
    let confirmations = 0;
    const harness = controllerHarness(async () => { confirmations++; });

    harness.controller.open();

    assert.equal(harness.elements["payment-total"].textContent, "100.00");
    assert.equal(harness.elements["payment-cash-amount"].value, "100.00");
    assert.equal(harness.elements["payment-received"].value, "100.00");
    assert.deepEqual(harness.modalActions, ["show"]);
    harness.elements["btn-cancel-payment"].dispatch("click");
    assert.equal(confirmations, 0);
});

test("default cash confirmation submits the exact total without opening a payment popup", async () => {
    const confirmations = [];
    const harness = controllerHarness(async payment => { confirmations.push(payment); });

    await harness.controller.confirmDefaultCash();

    assert.equal(confirmations.length, 1);
    assert.equal(confirmations[0].payment_method, "cash");
    assert.equal(confirmations[0].cash_amount, "100.00");
    assert.equal(confirmations[0].promptpay_amount, "0.00");
    assert.equal(confirmations[0].received_amount, "100.00");
    assert.equal(confirmations[0].change_amount, "0.00");
    assert.deepEqual(harness.modalActions, []);
});

test("payment modal restores stored mixed payment when its total is unchanged", () => {
    const harness = controllerHarness(async () => {}, () => ({
        payment_method: "mixed",
        cash_amount: "40.00",
        promptpay_amount: "60.00",
        received_amount: "50.00"
    }));

    harness.controller.open();

    assert.equal(harness.elements["payment-method"].value, "mixed");
    assert.equal(harness.elements["payment-mixed-cash"].value, "40.00");
    assert.equal(harness.elements["payment-promptpay-amount"].value, "60.00");
    assert.equal(harness.elements["payment-received"].value, "50.00");
    assert.equal(harness.elements["payment-change"].textContent, "10.00");
});

test("payment modal resets stored payment when the edited total changed", () => {
    const harness = controllerHarness(async () => {}, () => ({
        payment_method: "cash",
        cash_amount: "100.00",
        promptpay_amount: "0.00",
        received_amount: "150.00"
    }));

    harness.setTotal("120.00");
    harness.controller.open();

    assert.equal(harness.elements["payment-method"].value, "cash");
    assert.equal(harness.elements["payment-cash-amount"].value, "120.00");
    assert.equal(harness.elements["payment-received"].value, "120.00");
    assert.equal(harness.elements["payment-change"].textContent, "0.00");
});

test("new bill can explicitly reset the payment state before the next confirmation", () => {
    const harness = controllerHarness(async () => {});

    harness.controller.open();
    harness.elements["payment-method"].value = "mixed";
    harness.elements["payment-method"].dispatch("change");
    harness.elements["payment-mixed-cash"].value = "40.00";
    harness.elements["payment-received"].value = "50.00";
    harness.elements["payment-received"].dispatch("input");
    harness.setTotal("120.00");

    harness.controller.reset();

    assert.equal(harness.elements["payment-total"].textContent, "120.00");
    assert.equal(harness.elements["payment-method"].value, "cash");
    assert.equal(harness.elements["payment-cash-amount"].value, "120.00");
    assert.equal(harness.elements["payment-received"].value, "120.00");
    assert.equal(harness.elements["payment-promptpay-amount"].value, "0.00");
    assert.equal(harness.elements["payment-change"].textContent, "0.00");
});

test("mixed modal preview and confirm use decimal-safe derived values", async () => {
    const confirmations = [];
    const harness = controllerHarness(async payment => { confirmations.push(payment); });

    harness.controller.open();
    harness.elements["payment-method"].value = "mixed";
    harness.elements["payment-method"].dispatch("change");
    harness.elements["payment-mixed-cash"].value = "40.00";
    harness.elements["payment-received"].value = "50.00";
    harness.elements["payment-received"].dispatch("input");

    assert.equal(harness.elements["payment-promptpay-amount"].value, "60.00");
    assert.equal(harness.elements["payment-change"].textContent, "10.00");
    await harness.elements["btn-confirm-payment"].dispatch("click");
    assert.equal(confirmations.length, 1);
    assert.equal(confirmations[0].change_amount, "10.00");
});

test("changed total resets stale payment and blocks confirmation", async () => {
    let confirmations = 0;
    const harness = controllerHarness(async () => { confirmations++; });

    harness.controller.open();
    harness.elements["payment-received"].value = "100.00";
    harness.setTotal("120.00");
    await harness.elements["btn-confirm-payment"].dispatch("click");

    assert.equal(confirmations, 0);
    assert.equal(harness.elements["payment-total"].textContent, "120.00");
    assert.equal(harness.elements["payment-received"].value, "120.00");
    assert.equal(harness.elements["payment-error"].classList.contains("d-none"), false);
});

test("backend error keeps payment values and releases confirmation control", async () => {
    const harness = controllerHarness(async () => {
        throw new Error("Backend rejected payment");
    });

    harness.controller.open();
    harness.elements["payment-received"].value = "100.00";
    await harness.elements["btn-confirm-payment"].dispatch("click");

    assert.equal(harness.elements["payment-received"].value, "100.00");
    assert.equal(harness.elements["btn-confirm-payment"].disabled, false);
    assert.equal(harness.elements["payment-error"].textContent, "Backend rejected payment");
});
