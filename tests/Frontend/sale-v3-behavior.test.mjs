import assert from "node:assert/strict";
import fs from "node:fs";
import test from "node:test";
import vm from "node:vm";

const saleV3Source = fs.readFileSync(
    new URL("../../public/js/modules/sale-v3.js", import.meta.url),
    "utf8",
);
const posDateSource = fs.readFileSync(
    new URL("../../public/js/modules/pos-date.js", import.meta.url),
    "utf8",
);

class ClassList {
    constructor() {
        this.names = new Set();
    }

    add(...names) {
        names.forEach((name) => this.names.add(name));
    }

    remove(...names) {
        names.forEach((name) => this.names.delete(name));
    }

    toggle(name, force) {
        if (force === true) this.names.add(name);
        else if (force === false) this.names.delete(name);
        else if (this.names.has(name)) this.names.delete(name);
        else this.names.add(name);
    }

    contains(name) {
        return this.names.has(name);
    }
}

class FakeOption {
    constructor({ value = "", dataset = {}, textContent = "" } = {}) {
        this.value = String(value);
        this.dataset = dataset;
        this.textContent = textContent;
        this.disabled = false;
        this.hidden = false;
    }
}

class FakeElement {
    constructor({
        dataset = {},
        value = "",
        checked = false,
        tagName = "DIV",
        hidden = false,
    } = {}) {
        this.dataset = dataset;
        this._value = value;
        this.checked = checked;
        this.tagName = tagName;
        this.hidden = hidden;
        this.style = {};
        this.disabled = false;
        this.innerHTML = "";
        this.textContent = "";
        this.id = "";
        this.parentElement = { classList: new ClassList() };
        this.classList = new ClassList();
        this.listeners = new Map();
        this.options = [];
        this._selectedOptions = null;
    }

    set value(value) {
        this._value = String(value ?? "");
        this._selectedOptions = null;
    }

    get value() {
        return this._value;
    }

    set selectedOptions(options) {
        this._selectedOptions = options;
    }

    get selectedOptions() {
        if (this._selectedOptions) return this._selectedOptions;
        return this.options.filter((option) => option.value === this.value);
    }

    addEventListener(type, listener) {
        const listeners = this.listeners.get(type) || [];
        listeners.push(listener);
        this.listeners.set(type, listeners);
    }

    async dispatch(type, properties = {}) {
        const event = {
            type,
            target: this,
            preventDefault() {},
            ...properties,
        };
        for (const listener of this.listeners.get(type) || []) {
            await listener(event);
        }
    }

    dispatchEvent(event) {
        return this.dispatch(event.type, event);
    }

    querySelectorAll(selector) {
        if (selector === "option") return this.options;
        return [];
    }

    querySelector() {
        return null;
    }

    closest() {
        return this.parentElement;
    }

    setAttribute(name, value) {
        this[name] = String(value);
    }

    focus() {}

    select() {}

    append() {}

    remove() {
        this.removed = true;
    }
}

class FakeSelect extends FakeElement {
    constructor(options = []) {
        super({ tagName: "SELECT" });
        this.options = options;
    }
}

function zoneOption(zone) {
    return new FakeOption({
        value: zone.id,
        dataset: { zone: JSON.stringify(zone) },
        textContent: zone.name,
    });
}

function createHarness({
    addresses = [],
    zones = [],
    addressLoadError = null,
    addressLoadDeferred = null,
    simulateQuantityBackdropRace = false,
    product = {
        id: 1,
        name: "Test Product",
        unit: "piece",
        stock_qty: 20,
        price: 100,
        rounding_unit: 1,
        rounding_direction: "nearest",
        productUnits: [{
            id: 11,
            selling_price: 100,
            is_sale_unit: true,
            unit: { name: "piece" },
            price_tiers: [],
        }],
    },
} = {}) {
    const elements = new Map();
    const modalState = {
        quantityBackdropActive: false,
        confirmationOpened: 0,
    };
    const modalBackdrop = {
        remove() {
            modalState.quantityBackdropActive = false;
        },
    };
    const root = new FakeElement({
        dataset: {
            saleDate: "2026-08-05",
            addressUrlTemplate: "/customers/__CUSTOMER__/addresses",
            storeUrl: "/sales-v3",
            documentUrlTemplate: "/sales/__SALE__/invoice-v2",
        },
    });
    const productCard = new FakeElement({
        dataset: {
            product: JSON.stringify(product),
            search: product.name.toLowerCase(),
            category: "",
        },
    });
    const productPrice = new FakeElement();
    productCard.querySelector = (selector) => selector === ".v3-product-price" ? productPrice : null;

    function add(selector, element = new FakeElement()) {
        element.id = selector.replace(/^#/, "");
        elements.set(selector, element);
        return element;
    }

    const priceZoneSelect = add(
        "#v3-price-zone-select",
        new FakeSelect(zones.map(zoneOption)),
    );
    const addressSelect = add("#v3-address-id", new FakeSelect());
    const addressPicker = add("#v3-address-picker", new FakeElement({ hidden: true }));
    const customerSelect = add("#v3-customer-id", new FakeSelect());
    add("#pos-v3", root);
    add("#v3-quantity-input", new FakeElement({ value: "1" }));
    [
        "#v3-stock-only",
        "#v3-product-search",
        "#v3-cart-items",
        "#v3-cart-count",
        "#v3-delivery-fee",
        "#v3-subtotal",
        "#v3-total",
        "#v3-customer-address",
        "#v3-zone-status",
        "#v3-delivery-date",
        "#v3-delivery-date-display",
        "#v3-sale-date-display",
        "#v3-delivery-date-help",
        "#v3-pickup",
        "#v3-pickup-button",
        "#v3-delivery",
        "#v3-technician-id",
        "#v3-discount",
        "#v3-quantity-modal",
        "#v3-quantity-title",
        "#v3-quantity-stock",
        "#v3-quantity-sale-availability",
        "#v3-quantity-unit",
        "#v3-quantity-price",
        "#v3-quantity-total",
        "#v3-quantity-error",
        "#v3-quantity-confirm",
        "#v3-quantity-decrease",
        "#v3-quantity-increase",
        "#v3-note-button",
        "#v3-note-input",
        "#v3-note-modal",
        "#v3-note-confirm",
        "#v3-note-status",
        "#v3-new-bill",
        "#v3-clear-cart",
        "#v3-submit",
        "#v3-action-feedback",
        "#payment-confirmation-modal",
        "#pos-v3-clock",
    ].forEach((selector) => add(selector));
    elements.get("#v3-delivery-date").value = "2026-08-05";
    elements.get("#v3-delivery-date-display").value = "05/08/2026";
    elements.get("#v3-delivery-fee").parentElement = { classList: new ClassList() };
    elements.get("#v3-pickup").checked = true;
    elements.get("#v3-stock-only").checked = false;
    elements.get("#v3-discount").value = "0.00";
    elements.get("#v3-delivery-date").closest = () => ({
        classList: new ClassList(),
    });

    const document = {
        activeElement: elements.get("#v3-product-search"),
        body: { classList: new ClassList() },
        getElementById(id) {
            return id === "pos-v3" ? root : elements.get("#" + id) || null;
        },
        querySelector(selector) {
            if (selector === "#pos-v3") return root;
            if (selector === 'meta[name="csrf-token"]') {
                return { content: "test-token" };
            }
            return elements.get(selector) || null;
        },
        querySelectorAll(selector) {
            if (selector === ".v3-product-card") return [productCard];
            if (selector === ".v3-category" || selector === ".v3-filter") return [];
            if (selector === ".modal") return [elements.get("#v3-quantity-modal"), elements.get("#payment-confirmation-modal")];
            if (selector === ".modal-backdrop") return modalState.quantityBackdropActive ? [modalBackdrop] : [];
            return [];
        },
        addEventListener() {},
        createElement(tagName) {
            return new FakeElement({ tagName: String(tagName).toUpperCase() });
        },
    };

    const feedback = [];
    const stateContext = {};
    const window = {
        document,
        window: null,
        globalThis: null,
        Event: class Event {
            constructor(type) {
                this.type = type;
            }
        },
        confirm() {
            return true;
        },
        alert() {},
        setInterval() {
            return 1;
        },
        setTimeout(callback) {
            callback();
            return 1;
        },
        jQuery() {
            const element = arguments[0];
            return {
                one() {},
                modal(action) {
                    if (element?.id !== "v3-quantity-modal") return;
                    if (action === "show") {
                        modalState.quantityBackdropActive = true;
                        element.classList.add("show");
                        document.body.classList.add("modal-open");
                    }
                    if (action === "hide") {
                        if (simulateQuantityBackdropRace) {
                            modalState.quantityBackdropActive = true;
                            element.classList.add("show");
                            document.body.classList.add("modal-open");
                            return;
                        }
                        modalState.quantityBackdropActive = false;
                        element.classList.remove("show");
                        document.body.classList.remove("modal-open");
                    }
                },
            };
        },
        FinalPos: {
            configure(context) {
                Object.assign(stateContext, context);
            },
            syncCustomerDisplay() {},
            showFeedback(message, tone) {
                feedback.push({ message, tone });
            },
            openConfirmation() {
                if (!stateContext.canConfirmDelivery?.()) return false;
                modalState.confirmationOpened += 1;
                return true;
            },
            showSuccess() {},
        },
        PosPayment: {
            createController() {
                return {
                    payload(payment) {
                        return payment || {};
                    },
                };
            },
        },
        PosDate: {},
        ZonePricingMath: null,
        fetch: async (url) => {
            if (addressLoadDeferred) await addressLoadDeferred;
            if (addressLoadError) throw addressLoadError;
            const customerId = String(url).split("/").at(-2);
            return {
                ok: true,
                status: 200,
                async json() {
                    return customerId === "7" ? addresses : [];
                },
            };
        },
        console,
    };
    window.window = window;
    window.globalThis = window;

    vm.runInNewContext(posDateSource, window);
    vm.runInNewContext(saleV3Source, window);

    return {
        addressPicker,
        addressSelect,
        customerSelect,
        elements,
        feedback,
        priceZoneSelect,
        productCard,
        productPrice,
        state: stateContext.state,
        context: stateContext,
        modalState,
        async clickSubmit() {
            if (modalState.quantityBackdropActive) return false;
            await elements.get("#v3-submit").dispatch("click");
            return true;
        },
        window,
    };
}

const activeZone = {
    id: 1,
    name: "Active Zone",
    active: true,
    price_markup_percent: 0,
    rounding_increment: "0.25",
    minimum_profit: 0,
};

test("multiple addresses automatically select the address marked as default", async () => {
    const harness = createHarness({
        addresses: [
            { id: 101, address: "First site", is_default: false, delivery_zone: activeZone },
            { id: 102, address: "Default site", is_default: true, delivery_zone: { ...activeZone, id: 2, name: "Default Zone" } },
        ],
        zones: [activeZone, { ...activeZone, id: 2, name: "Default Zone" }],
    });

    harness.context.setDeliveryType("delivery");
    await harness.context.setCustomer("7");

    assert.equal(harness.context.state.addressId, "102");
    assert.equal(harness.context.state.address.address, "Default site");
    assert.equal(harness.context.state.zone.name, "Default Zone");
    assert.equal(harness.addressSelect.value, "102");
    assert.equal(harness.addressPicker.hidden, false);
    assert.equal(harness.addressPicker.classList.contains("d-none"), false);
});

test("changing to a customer without a default address clears the previous address and zone", async () => {
    const harness = createHarness({
        addresses: [
            { id: 201, address: "Site A", is_default: false, delivery_zone: activeZone },
            { id: 202, address: "Site B", is_default: false, delivery_zone: { ...activeZone, id: 2, name: "Zone B" } },
        ],
        zones: [activeZone],
    });
    harness.context.setDeliveryType("delivery");
    harness.context.state.addressId = "99";
    harness.context.state.address = { id: 99, address: "Previous site", delivery_zone: activeZone };
    harness.context.state.zone = activeZone;
    harness.context.state.draftZone = activeZone;

    await harness.context.setCustomer("7");

    assert.equal(harness.context.state.addressId, "");
    assert.equal(harness.context.state.address, null);
    assert.equal(harness.context.state.zone, null);
    assert.equal(harness.context.state.draftZone, null);
    assert.equal(harness.priceZoneSelect.value, "");
});

test("one address is selected automatically and remains visible in the customer summary", async () => {
    const harness = createHarness({
        addresses: [{ id: 101, address: "Only site", delivery_zone: activeZone }],
    });

    harness.context.setDeliveryType("delivery");
    await harness.context.setCustomer("7");

    assert.equal(harness.context.state.addressId, "101");
    assert.equal(harness.context.state.address.address, "Only site");
    assert.equal(harness.addressPicker.hidden, true);
});

test("the price zone mirrors the selected address and cannot override it", async () => {
    const secondZone = {
        ...activeZone,
        id: 2,
        name: "Markup Zone",
        price_markup_percent: 20,
    };
    const harness = createHarness({
        addresses: [{ id: 101, address: "Active site", is_default: true, delivery_zone: activeZone }],
        zones: [activeZone, secondZone],
    });

    harness.context.setDeliveryType("delivery");
    await harness.context.setCustomer("7");
    await harness.productCard.dispatch("click");
    harness.elements.get("#v3-quantity-input").value = "2";
    await harness.elements.get("#v3-quantity-confirm").dispatch("click");

    assert.equal(harness.context.state.cart[0].price, 100);

    assert.equal(harness.priceZoneSelect.value, String(activeZone.id));
    assert.equal(harness.priceZoneSelect.disabled, true);

    harness.priceZoneSelect.value = String(secondZone.id);
    harness.priceZoneSelect.selectedOptions = [harness.priceZoneSelect.options[1]];
    await harness.priceZoneSelect.dispatch("change");

    assert.equal(harness.context.state.zone.id, activeZone.id);
    assert.equal(harness.context.state.cart[0].price, 100);
    assert.equal(harness.elements.get("#v3-total").textContent, "200.00");
});

test("an address with an inactive delivery zone cannot be used for confirmation", async () => {
    const inactiveZone = { ...activeZone, id: 9, name: "Inactive Zone", active: false };
    const harness = createHarness({
        addresses: [{ id: 901, address: "Inactive site", is_default: true, delivery_zone: inactiveZone }],
        zones: [inactiveZone],
    });

    harness.context.setDeliveryType("delivery");
    await harness.context.setCustomer("7");

    assert.equal(harness.context.state.zone, null);
    assert.equal(harness.context.state.draftZone, null);
    assert.equal(harness.addressSelect.value, "");
    assert.equal(harness.context.canConfirmDelivery(), false);
});

test("an address loading failure clears zone state and reports a recoverable error", async () => {
    const harness = createHarness({
        zones: [activeZone],
        addressLoadError: new Error("network unavailable"),
    });
    harness.context.setDeliveryType("delivery");
    harness.context.state.address = { id: 99, delivery_zone: activeZone };
    harness.context.state.addressId = "99";
    harness.context.state.zone = activeZone;
    harness.context.state.draftZone = activeZone;

    await harness.context.setCustomer("7");

    assert.equal(harness.context.state.address, null);
    assert.equal(harness.context.state.addressId, "");
    assert.equal(harness.context.state.zone, null);
    assert.equal(harness.context.state.draftZone, null);
    assert.equal(harness.context.state.addressLoading, false);
    assert.equal(
        harness.feedback.some((entry) => entry.tone === "error" && /โหลดที่อยู่และโซนไม่สำเร็จ/.test(entry.message)),
        true,
    );
    assert.equal(harness.context.canConfirmDelivery(), false);
});

test("address loading exposes a pending state until the response is ready", async () => {
    let release;
    const pending = new Promise((resolve) => { release = resolve; });
    const harness = createHarness({
        addresses: [{ id: 101, address: "Only site", is_default: true, delivery_zone: activeZone }],
        zones: [activeZone],
        addressLoadDeferred: pending,
    });

    const load = harness.context.setCustomer("7");
    await Promise.resolve();

    assert.equal(harness.context.state.addressLoading, true);
    assert.equal(harness.addressSelect.disabled, true);
    assert.match(harness.elements.get("#v3-zone-status").textContent, /กำลังโหลด/);

    release();
    await load;
    assert.equal(harness.context.state.addressLoading, false);
});

test("changing address reprices both the product card and cart", async () => {
    const markupZone = { ...activeZone, id: 2, name: "Markup Zone", price_markup_percent: 20 };
    const harness = createHarness({
        addresses: [
            { id: 101, address: "Base site", is_default: true, delivery_zone: activeZone },
            { id: 102, address: "Markup site", is_default: false, delivery_zone: markupZone },
        ],
        zones: [activeZone, markupZone],
    });
    harness.context.setDeliveryType("delivery");
    await harness.context.setCustomer("7");
    await harness.productCard.dispatch("click");
    await harness.elements.get("#v3-quantity-confirm").dispatch("click");

    harness.context.setAddress("102");
    await Promise.resolve();

    assert.equal(harness.context.state.zone.id, 2);
    assert.equal(harness.context.state.cart[0].price, 120);
    assert.equal(harness.productPrice.textContent, "120.00");
});

test("sale quantity is converted to base quantity before checking stock", async () => {
    const harness = createHarness({
        product: {
            id: 1,
            name: "Converted Product",
            unit: "piece",
            stock_qty: 10,
            price: 400,
            rounding_unit: 1,
            rounding_direction: "nearest",
            productUnits: [{
                id: 11,
                selling_price: 400,
                conversion_rate: 4,
                is_sale_unit: true,
                unit: { name: "box" },
                price_tiers: [],
            }],
        },
    });
    await harness.productCard.dispatch("click");
    assert.equal(
        harness.elements.get("#v3-quantity-sale-availability").textContent,
        "ขายได้สูงสุด 2.50 box · 1 box = 4.00 piece",
    );
    harness.elements.get("#v3-quantity-input").value = "3";

    await harness.elements.get("#v3-quantity-confirm").dispatch("click");

    assert.equal(harness.context.state.cart.length, 0);
    assert.match(harness.elements.get("#v3-quantity-error").textContent, /10\.00 piece/);
});

test("repeated adds cannot exceed aggregate base stock", async () => {
    const harness = createHarness({
        product: {
            id: 1,
            name: "Converted Product",
            unit: "piece",
            stock_qty: 10,
            price: 400,
            rounding_unit: 1,
            rounding_direction: "nearest",
            productUnits: [{
                id: 11,
                selling_price: 400,
                conversion_rate: 4,
                is_sale_unit: true,
                unit: { name: "box" },
                price_tiers: [],
            }],
        },
    });

    await harness.productCard.dispatch("click");
    harness.elements.get("#v3-quantity-input").value = "2";
    await harness.elements.get("#v3-quantity-confirm").dispatch("click");

    await harness.productCard.dispatch("click");
    harness.elements.get("#v3-quantity-input").value = "1";
    await harness.elements.get("#v3-quantity-confirm").dispatch("click");

    assert.equal(harness.context.state.cart[0].qty, 2);
    assert.match(harness.elements.get("#v3-quantity-error").textContent, /10\.00 piece/);
});

test("editing one sale unit cannot exceed aggregate base stock across units", async () => {
    const product = {
        id: 1,
        name: "Multi-unit Product",
        unit: "piece",
        stock_qty: 10,
        price: 100,
        rounding_unit: 1,
        rounding_direction: "nearest",
        productUnits: [],
    };
    const boxUnit = {
        id: 11,
        selling_price: 400,
        conversion_rate: 4,
        is_sale_unit: true,
        unit: { name: "box" },
        price_tiers: [],
    };
    const pieceUnit = {
        id: 12,
        selling_price: 100,
        conversion_rate: 1,
        is_sale_unit: false,
        unit: { name: "piece" },
        price_tiers: [],
    };
    product.productUnits = [boxUnit, pieceUnit];
    const harness = createHarness({ product });
    harness.context.state.cart = [
        { productId: 1, product, unit: boxUnit, qty: 2, price: 400 },
        { productId: 1, product, unit: pieceUnit, qty: 1, price: 100 },
    ];
    harness.context.render();

    const quantityInput = {
        value: "3",
        dataset: { index: "1" },
        matches(selector) { return selector === ".v3-cart-quantity"; },
        closest() {
            return { querySelector() { return { textContent: "" }; } };
        },
    };
    await harness.elements.get("#v3-cart-items").dispatch("input", { target: quantityInput });

    assert.equal(harness.context.state.cart[1].qty, 1);
});

test("stale address responses cannot repopulate a reset sale", async () => {
    let release;
    const pending = new Promise((resolve) => { release = resolve; });
    const harness = createHarness({
        addresses: [{ id: 101, address: "Only site", is_default: true, delivery_zone: activeZone }],
        addressLoadDeferred: pending,
    });

    const load = harness.context.setCustomer("7");
    await Promise.resolve();
    harness.context.state.customerId = "";
    harness.context.state.addressId = "";
    harness.context.state.address = null;
    harness.context.state.addresses = [];

    release();
    await load;

    assert.deepEqual(harness.context.state.addresses, []);
    assert.equal(harness.context.state.addressId, "");
    assert.equal(harness.context.state.address, null);
    assert.equal(harness.addressSelect.value, "");
});

test("delivery confirmation requires a customer, address, active zone, and valid date", async () => {
    const harness = createHarness({
        addresses: [{ id: 101, address: "Only site", delivery_zone: activeZone }],
    });

    harness.context.setDeliveryType("delivery");
    assert.equal(harness.context.canConfirmDelivery(), false);

    await harness.context.setCustomer("7");
    assert.equal(harness.context.canConfirmDelivery(), true);

    harness.context.state.address = null;
    harness.context.state.addressId = "";
    assert.equal(harness.context.canConfirmDelivery(), false);
});

test("invalid delivery date clears the hidden ISO value and blocks confirmation", async () => {
    const harness = createHarness({
        addresses: [{ id: 101, address: "Only site", delivery_zone: activeZone }],
    });

    harness.context.setDeliveryType("delivery");
    await harness.context.setCustomer("7");
    assert.equal(harness.context.canConfirmDelivery(), true);

    const display = harness.elements.get("#v3-delivery-date-display");
    const hidden = harness.elements.get("#v3-delivery-date");
    display.value = "31/02/2026";
    await display.dispatch("input");

    assert.equal(hidden.value, "");
    assert.equal(harness.context.canConfirmDelivery(), false);

    display.value = "05/08/2026";
    await display.dispatch("input");

    assert.equal(hidden.value, "2026-08-05");
    assert.equal(harness.context.canConfirmDelivery(), true);
});

test("delivery confirmation survives a quick submit after the quantity modal closes", async () => {
    const harness = createHarness({
        simulateQuantityBackdropRace: true,
        addresses: [{ id: 101, address: "Only site", delivery_zone: activeZone }],
    });

    harness.context.setDeliveryType("delivery");
    await harness.context.setCustomer("7");
    await harness.productCard.dispatch("click");
    await harness.elements.get("#v3-quantity-confirm").dispatch("click");

    const clickDelivered = await harness.clickSubmit();

    assert.equal(clickDelivered, true);
    assert.equal(harness.modalState.quantityBackdropActive, false);
    assert.equal(harness.modalState.confirmationOpened, 1);
});
