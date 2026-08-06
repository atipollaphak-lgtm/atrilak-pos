(function () {
    const root = document.getElementById("pos-v3");
    if (!root) return;

    const state = { cart: [], customerId: "", addressId: "", deliveryType: "pickup", address: null, addresses: [], addressLoading: false, draftZone: null, zone: null, deliveryFee: 0, deliveryFeeEdited: false, discount: 0, note: "", holdBillId: null, activeProduct: null, filter: "all", category: "" };
    let addressLoadSequence = 0;
    const $ = (selector) => document.querySelector(selector);
    const money = (value) => Number(value || 0).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const escapeHtml = (value) => String(value ?? "").replace(/[&<>'"]/g, (char) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "'": "&#39;", '"': "&quot;" }[char]));
    const deliveryDateField = () => $("#v3-delivery-date") || $("#v3-sale-date");
    const todayForSale = () => root.dataset.saleDate || new Date().toISOString().slice(0, 10);
    const resetDeliveryDate = () => { const field = deliveryDateField(); if (field) field.value = todayForSale(); const display = $("#v3-delivery-date-display"); if (display && window.PosDate) display.value = window.PosDate.formatDisplay(todayForSale()); $("#v3-sale-date-display").textContent = window.PosDate ? window.PosDate.formatDisplay(todayForSale()) : todayForSale(); };
    const effectiveZone = () => state.deliveryType === "delivery" ? state.zone : null;
    const zoneIsActive = (zone) => Boolean(zone && (zone.active === true || zone.active === 1 || zone.active === "1"));
    const deliveryDateIsValid = () => {
        const hidden = deliveryDateField();
        const display = $("#v3-delivery-date-display");
        if (!hidden) return true;
        if (!display) return Boolean(hidden.value);
        const iso = window.PosDate?.toIso(display.value);
        return Boolean(iso && hidden.value === iso);
    };
    const addressLabel = (address) => address?.address || address?.label || address?.name || (address?.id ? `ที่อยู่ ${address.id}` : "ยังไม่ได้เลือกที่อยู่");
    const unitFor = (product, id = null) => product.productUnits?.find((unit) => String(unit.id) === String(id)) || product.productUnits?.find((unit) => unit.is_sale_unit) || product.productUnits?.[0] || { id: null, selling_price: product.price, unit: { name: product.unit || "หน่วย" }, barcodes: [] };
    const conversionRateFor = (unit) => Math.max(Number(unit?.conversion_rate || 1), 0);
    const requiredBaseStock = (qty, unit) => Number(qty || 0) * conversionRateFor(unit);
    const availableSaleQuantity = (product, unit) => Math.floor(((Number(product.stock_qty || 0) / conversionRateFor(unit)) + Number.EPSILON) * 100) / 100;
    const cartBaseStock = (product, excludedIndex = -1) => state.cart.reduce((totalStock, item, index) => index === excludedIndex || String(item.productId) !== String(product.id) ? totalStock : totalStock + requiredBaseStock(item.qty, item.unit), 0);
    const roundPrice = (value, product) => { const unit = Number(product.rounding_unit || 5); const direction = product.rounding_direction || "up"; const quotient = value / unit; const rounded = direction === "down" ? Math.floor(quotient) : direction === "nearest" ? Math.round(quotient) : Math.ceil(quotient); return rounded * unit; };
    const unitPrice = (unit, qty, product) => { const tierPrice = (unit.price_tiers || []).reduce((price, tier) => Number(qty) >= Number(tier.min_qty) ? (tier.fixed_price !== null && tier.fixed_price !== "" ? Number(tier.fixed_price) : Number(unit.selling_price) * (1 - Number(tier.discount_percent || 0) / 100)) : price, Number(unit.selling_price || product.price || 0)); const zone = effectiveZone(); const delivery = zoneIsActive(zone); const roundingIncrement = product.category_rounding_override || zone?.rounding_increment || "0.25"; if (delivery && window.ZonePricingMath) return Number(window.ZonePricingMath.ceilAfterMarkup(tierPrice, zone.price_markup_percent || 0, roundingIncrement)); const markup = delivery ? Number(zone.price_markup_percent || 0) : 0; return roundPrice(tierPrice * (1 + markup / 100), product); };
    const total = () => state.cart.reduce((sum, item) => sum + Number(item.qty) * Number(item.price), 0) + state.deliveryFee - state.discount;
    function updateDeliveryPreview() { if (state.deliveryType === "pickup") { state.deliveryFee = 0; return; } if (state.deliveryFeeEdited) return; if (!state.zone) { state.deliveryFee = 0; return; } const profit = state.cart.reduce((sum, item) => sum + Number(item.qty) * (Number(item.price) - Number(item.product.cost_price || 0) * Number(item.unit.conversion_rate || 1)), 0) - state.discount; state.deliveryFee = Math.max(0, Number(state.zone.minimum_profit || 0) - profit); }
    function syncCartTotals() { updateDeliveryPreview(); $("#v3-cart-count").textContent = state.cart.length; $("#v3-delivery-fee").value = money(state.deliveryFee); $("#v3-subtotal").textContent = money(state.cart.reduce((sum, item) => sum + item.qty * item.price, 0)); $("#v3-total").textContent = money(total()); }

    function add(product, unitId = null, qty = 1) {
        const unit = unitFor(product, unitId);
        if (cartBaseStock(product) + requiredBaseStock(qty, unit) > Number(product.stock_qty) + 0.00005) {
            $("#v3-quantity-error").textContent = `สต็อกคงเหลือ ${money(product.stock_qty)} ${product.unit || "หน่วย"}`;
            return false;
        }
        const key = `${product.id}:${unit.id || "base"}`;
        const existing = state.cart.find((item) => item.key === key);
        if (existing) { const wasEdited = Boolean(existing.priceWasEdited); const salePrice = existing.price; existing.qty += Number(qty); const systemPrice = unitPrice(unit, existing.qty, product); existing.originalPrice = wasEdited ? systemPrice : null; existing.price = wasEdited ? salePrice : systemPrice; }
        else state.cart.push({ key, product, productId: product.id, productUnitId: unit.id, unit, unitName: unit.unit?.name || product.unit || "หน่วย", name: product.name, qty: Number(qty), price: unitPrice(unit, qty, product) });
        render();
        return true;
    }

    function repriceCart() { state.cart.forEach((item) => { const systemPrice = unitPrice(item.unit, item.qty, item.product); if (item.priceWasEdited) item.originalPrice = systemPrice; else item.price = systemPrice; }); }

    function ensurePriceMetadata() { state.cart.forEach((item) => { if (!Object.prototype.hasOwnProperty.call(item, "priceWasEdited")) item.priceWasEdited = false; if (!Object.prototype.hasOwnProperty.call(item, "originalPrice")) item.originalPrice = null; if (!Object.prototype.hasOwnProperty.call(item, "priceChangedSinceHold")) item.priceChangedSinceHold = false; }); }

    function refreshPricingContext() {
        repriceCart();
        document.querySelectorAll(".v3-product-card").forEach((card) => { const product = JSON.parse(card.dataset.product); const label = card.querySelector(".v3-product-price"); if (label) label.textContent = money(unitPrice(unitFor(product), 1, product)); });
        const zone = effectiveZone();
        const zoneLabel = $("#v3-address-zone"); if (zoneLabel) zoneLabel.textContent = zone?.name ? `โซนจัดส่ง: ${zone.name} +${money(zone.price_markup_percent || 0)}%` : "โซนจัดส่ง: -";
        const addressZone = state.address?.delivery_zone || null;
        const zoneSelect = $("#v3-price-zone-select"); if (zoneSelect) { zoneSelect.disabled = true; zoneSelect.value = zoneIsActive(addressZone) ? String(addressZone.id) : ""; }
        const zoneStatus = $("#v3-zone-status");
        if (zoneStatus) zoneStatus.textContent = state.addressLoading ? "กำลังโหลดที่อยู่และโซน..." : addressZone?.name ? `โซนตามที่อยู่: ${addressZone.name}` : state.customerId ? "ยังไม่ได้เลือกที่อยู่หรือไม่พบโซน" : "เลือกลูกค้าเพื่อโหลดโซน";
    }

    function syncFulfillmentUi() {
        const pickup = state.deliveryType === "pickup";
        const checkbox = $("#v3-pickup");
        if (checkbox) checkbox.checked = pickup;

        const pickupButton = $("#v3-pickup-button");
        const deliveryButton = $("#v3-delivery");
        const buttons = [
            [pickupButton, pickup],
            [deliveryButton, !pickup],
        ];

        buttons.forEach(([button, selected]) => {
            if (!button) return;
            button.classList.toggle("active", selected);
            button.classList.toggle("is-selected", selected);
            button.setAttribute("aria-pressed", selected ? "true" : "false");
            const check = button.querySelector(".fulfillment-check");
            if (check) check.hidden = !selected;
        });

        pickupButton?.classList.toggle("btn-primary", pickup);
        pickupButton?.classList.toggle("btn-outline-primary", !pickup);
        deliveryButton?.classList.toggle("btn-success", !pickup);
        deliveryButton?.classList.toggle("btn-outline-success", pickup);
        const feeInput = $("#v3-delivery-fee");
        feeInput?.parentElement?.classList.toggle("d-none", pickup);
        deliveryDateField()?.closest(".v3-delivery-date-field")?.classList.toggle("d-none", pickup);
    }

    function commitUnitPrice(index, rawValue) {
        const item = state.cart[index];
        const value = String(rawValue ?? "").trim();
        if (!item || !/^\d+(?:\.\d{0,2})?$/.test(value)) return false;
        const price = Number(value);
        if (!Number.isFinite(price) || price <= 0) return false;
        const changed = Math.abs(price - Number(item.price)) > 0.005;
        const systemPrice = unitPrice(item.unit, item.qty, item.product);

        if (changed) {
            item.price = price;
            item.priceWasEdited = true;
            item.originalPrice = systemPrice;
            item.priceChangedSinceHold = Boolean(item.priceChangedSinceHold || state.holdBillId);
        } else if (item.priceWasEdited) {
            item.originalPrice = systemPrice;
        } else {
            item.price = price;
            item.originalPrice = null;
            item.priceWasEdited = false;
            item.priceChangedSinceHold = false;
        }

        return true;
    }

    function syncInlinePriceInputs() {
        const container = $("#v3-cart-items");
        if (!container) return;
        container.querySelectorAll(".v3-cart-row").forEach((row) => {
            const index = Number(row.dataset.index);
            const item = state.cart[index];
            const current = row.querySelector(".v3-cart-unit-price");
            if (!current || !item || current.tagName === "INPUT") return;
            const input = document.createElement("input");
            input.className = "v3-cart-unit-price";
            input.dataset.index = String(index);
            input.value = Number(item.price).toFixed(2);
            input.inputMode = "decimal";
            input.setAttribute("aria-label", `ราคาต่อหน่วย ${item.name}`);
            input.title = item.priceWasEdited ? "ราคาแก้ไขเฉพาะบิล" : "ราคาต่อหน่วย";
            input.classList.toggle("price-override", Boolean(item.priceWasEdited));
            current.replaceWith(input);
        });
    }

    function syncNoteUi() {
        const status = $("#v3-note-status");
        if (!status) return;
        const preview = state.note.length > 48 ? `${state.note.slice(0, 48)}…` : state.note;
        status.textContent = state.note ? `มีหมายเหตุแล้ว (${preview})` : "ยังไม่มีหมายเหตุ";
        status.title = state.note || "";
        status.setAttribute("aria-label", state.note ? `มีหมายเหตุแล้ว: ${state.note}` : "ยังไม่มีหมายเหตุ");
        status.tabIndex = state.note ? 0 : -1;
        status.classList.toggle("has-note", Boolean(state.note));
    }

    function render() {
        ensurePriceMetadata();
        syncFulfillmentUi();
        updateDeliveryPreview();
        const container = $("#v3-cart-items");
        if (!state.cart.length) container.innerHTML = '<div class="pos-v3-empty">ยังไม่มีสินค้า<br><small>คลิกสินค้า หรือยิง Barcode เพื่อเริ่มขาย</small></div>';
        else container.innerHTML = state.cart.map((item, index) => `<div class="v3-cart-row" data-index="${index}"><input class="v3-cart-quantity" data-index="${index}" value="${item.qty}" inputmode="decimal" aria-label="จำนวน ${escapeHtml(item.name)}"><div class="v3-cart-product"><strong>${escapeHtml(item.name)}</strong><small>${escapeHtml(item.unitName)}</small></div><strong class="v3-cart-unit-price">${money(item.price)}</strong><strong class="v3-line-total">${money(item.qty * item.price)}</strong><button class="v3-cart-remove" data-action="remove" title="ลบ ${escapeHtml(item.name)}" aria-label="ลบ ${escapeHtml(item.name)}">×</button></div>`).join("");
        syncInlinePriceInputs();
        $("#v3-subtotal").textContent = money(state.cart.reduce((sum, item) => sum + item.qty * item.price, 0));
        $("#v3-cart-count").textContent = state.cart.length;
        $("#v3-delivery-fee").value = money(state.deliveryFee);
        $("#v3-total").textContent = money(total());
        const selectedAddress = state.address;
        const zoneName = effectiveZone()?.name || "";
        const addressText = selectedAddress ? addressLabel(selectedAddress) : (state.addresses.length > 1 ? `มี ${state.addresses.length} ที่อยู่ — กรุณาเลือก` : "เลือกที่อยู่จัดส่งเพื่อเริ่มคำนวณโซน");
        const delivery = state.deliveryType === "delivery";
        const fulfillmentText = delivery ? `จัดส่ง · ค่าส่ง ${money(state.deliveryFee)} บาท` : "รับเอง · ค่าส่ง 0.00 บาท";
        const fulfillmentClass = delivery ? "is-delivery" : "is-pickup";
        $("#v3-customer-address").innerHTML = `<i class="fas fa-map-marker-alt"></i> ${escapeHtml(addressText)}${zoneName ? ` <span class="badge badge-success ml-2">โซน: ${escapeHtml(zoneName)}</span>` : ""} <span id="v3-fulfillment-status" class="pos-v3-fulfillment-status ${fulfillmentClass} ml-2">${fulfillmentText}</span>`;
        syncNoteUi();
        window.FinalPos?.syncCustomerDisplay();
    }

    function filterProducts() {
        const keyword = $("#v3-product-search").value.trim().toLowerCase();
        document.querySelectorAll(".v3-product-card").forEach((card) => {
            const product = JSON.parse(card.dataset.product);
            const matchText = !keyword || card.dataset.search.includes(keyword) || product.productUnits?.some((u) => u.barcodes?.some((b) => String(b.barcode).toLowerCase().includes(keyword)));
            const matchCategory = !state.category || card.dataset.category === state.category;
            const matchStock = !$("#v3-stock-only").checked || Number(product.stock_qty) > 0;
            card.hidden = !(matchText && matchCategory && matchStock);
        });
    }

    function syncQuantityPreview() {
        const active = state.activeProduct;
        if (!active) return;
        const unit = unitFor(active.product, active.unitId);
        const quantity = Number($("#v3-quantity-input")?.value || 0);
        const currentPrice = active.existingIndex >= 0 ? state.cart[active.existingIndex]?.price : unitPrice(unit, quantity || 1, active.product);
        const saleUnitName = unit.unit?.name || active.product.unit || "หน่วย";
        const baseUnitName = active.product.unit || "หน่วย";
        const conversionRate = conversionRateFor(unit);
        $("#v3-quantity-unit").textContent = saleUnitName;
        $("#v3-quantity-price").textContent = money(currentPrice);
        $("#v3-quantity-total").textContent = money(quantity * Number(currentPrice || 0));
        const availability = $("#v3-quantity-sale-availability");
        if (availability) {
            const conversionText = conversionRate !== 1 || saleUnitName !== baseUnitName
                ? ` · 1 ${saleUnitName} = ${money(conversionRate)} ${baseUnitName}`
                : "";
            availability.textContent = `ขายได้สูงสุด ${money(availableSaleQuantity(active.product, unit))} ${saleUnitName}${conversionText}`;
        }
    }

    function openQuantity(product, unitId = null, existingIndex = -1) {
        state.activeProduct = { product, unitId, existingIndex };
        $("#v3-quantity-title").textContent = product.name;
        $("#v3-quantity-stock").textContent = `${money(product.stock_qty)} ${product.unit || "หน่วย"}`;
        $("#v3-quantity-input").value = existingIndex >= 0 ? state.cart[existingIndex].qty : "1";
        $("#v3-quantity-error").textContent = "";
        syncQuantityPreview();
        $("#v3-quantity-modal").dataset.returnFocus = document.activeElement?.id || "v3-product-search";
        window.jQuery($("#v3-quantity-modal")).one("shown.bs.modal", () => { $("#v3-quantity-input").focus(); $("#v3-quantity-input").select(); });
        window.jQuery($("#v3-quantity-modal")).one("hidden.bs.modal", () => $("#" + $("#v3-quantity-modal").dataset.returnFocus)?.focus());
        window.jQuery($("#v3-quantity-modal")).modal("show");
    }

    function closeQuantityModal() {
        const modal = $("#v3-quantity-modal");
        if (!modal) return;
        window.jQuery(modal).modal("hide");
        modal.classList.remove("show");
        if (modal.style) modal.style.display = "none";
        modal.setAttribute("aria-hidden", "true");

        const anotherModalIsOpen = Array.from(document.querySelectorAll(".modal"))
            .some((candidate) => candidate !== modal && candidate.classList.contains("show"));
        if (!anotherModalIsOpen) {
            document.body?.classList.remove("modal-open");
            document.querySelectorAll(".modal-backdrop").forEach((backdrop) => backdrop.remove());
        }
    }

    function confirmQuantity() {
        const qty = Number($("#v3-quantity-input").value);
        if (!Number.isFinite(qty) || qty < 0) { $("#v3-quantity-error").textContent = "จำนวนไม่ถูกต้อง"; return; }
        const { product, unitId, existingIndex } = state.activeProduct;
        const unit = unitFor(product, unitId);
        if (requiredBaseStock(qty, unit) > Number(product.stock_qty) + 0.00005) { $("#v3-quantity-error").textContent = `สต็อกคงเหลือ ${money(product.stock_qty)} ${product.unit || "หน่วย"}`; return; }
        if (existingIndex >= 0) { if (qty === 0) state.cart.splice(existingIndex, 1); else { state.cart[existingIndex].qty = qty; const currentItem = state.cart[existingIndex]; currentItem.qty = qty; const systemPrice = unitPrice(currentItem.unit, qty, currentItem.product); if (currentItem.priceWasEdited) currentItem.originalPrice = systemPrice; else currentItem.price = systemPrice; } }
        else if (qty > 0 && !add(product, unitId, qty)) return;
        render(); closeQuantityModal();
    }

    function sameZone(left, right) { return String(left?.id || "") === String(right?.id || ""); }

    function confirmZoneChange(nextZone, reason = "โซนจัดส่ง") {
        if (nextZone && !zoneIsActive(nextZone)) {
            window.FinalPos?.showFeedback("โซนจัดส่งนี้ปิดใช้งานอยู่ กรุณาเลือกโซนอื่น", "error");
            return false;
        }
        const previousZone = state.zone;
        if (sameZone(previousZone, nextZone)) return true;
        const before = state.cart.map((item) => Number(item.price));
        state.zone = nextZone || null;
        const preview = state.cart.map((item) => unitPrice(item.unit, item.qty, item.product));
        state.zone = previousZone;
        const changed = before.some((price, index) => Math.abs(price - Number(preview[index] || 0)) > 0.005);
        if (changed && state.cart.length && !window.confirm(`${reason}ใหม่อาจเปลี่ยนราคาสินค้าทั้งตะกร้า ต้องการดำเนินการต่อหรือไม่?`)) return false;
        state.zone = nextZone || null;
        repriceCart();
        return true;
    }

    function applyAddressSelection(addressId) {
        const nextAddress = state.addresses.find((address) => String(address.id) === String(addressId));
        const nextZone = nextAddress?.delivery_zone || null;
        if (nextZone && !zoneIsActive(nextZone)) {
            state.addressId = "";
            state.address = null;
            state.zone = null;
            state.draftZone = null;
            $("#v3-address-id").value = "";
            window.FinalPos?.showFeedback("ที่อยู่นี้ใช้โซนจัดส่งที่ปิดใช้งาน กรุณาเลือกที่อยู่อื่น", "error");
            refreshPricingContext();
            render();
            return false;
        }
        state.addressId = nextAddress ? String(nextAddress.id) : "";
        state.address = nextAddress || null;
        state.zone = state.deliveryType === "delivery" ? nextZone : null;
        state.draftZone = nextZone;
        state.deliveryFee = 0;
        state.deliveryFeeEdited = false;
        refreshPricingContext();
        render();
        return true;
    }

    async function loadAddresses(customerId = null, preferredAddressId = null) {
        if (customerId?.target) customerId = null;
        const id = customerId === null ? $("#v3-customer-id").value : String(customerId || "");
        const select = $("#v3-address-id");
        const requestSequence = ++addressLoadSequence;
        state.customerId = id;
        $("#v3-customer-id").value = id;
        select.innerHTML = id ? '<option>กำลังโหลด...</option>' : '<option value="">เลือกลูกค้าก่อน</option>';
        select.disabled = true;
        state.addressId = "";
        state.address = null;
        state.addresses = [];
        state.addressLoading = Boolean(id);
        state.zone = null;
        state.draftZone = null;
        const addressPicker = $("#v3-address-picker");
        if (addressPicker) {
            addressPicker.hidden = true;
            addressPicker.classList.add("d-none");
        }
        state.deliveryFee = 0;
        state.deliveryFeeEdited = false;
        refreshPricingContext();
        render();
        if (!id) { state.addressLoading = false; refreshPricingContext(); render(); return; }

        try {
            const response = await fetch(root.dataset.addressUrlTemplate.replace("__CUSTOMER__", id), { headers: { Accept: "application/json" } });
            if (!response.ok) throw new Error(`Address request failed with status ${response.status}`);
            const addresses = await response.json();
            if (requestSequence !== addressLoadSequence || state.customerId !== id) return;
            state.addresses = Array.isArray(addresses) ? addresses : [];
            state.addressLoading = false;
        } catch (error) {
            if (requestSequence !== addressLoadSequence || state.customerId !== id) return;
            state.addressLoading = false;
            state.addresses = [];
            select.innerHTML = '<option value="">โหลดที่อยู่ไม่สำเร็จ</option>';
            select.disabled = true;
            window.FinalPos?.showFeedback("โหลดที่อยู่และโซนไม่สำเร็จ กรุณาเลือกลูกค้าอีกครั้งเพื่อลองใหม่", "error");
            refreshPricingContext();
            render();
            return;
        }

        if (addressPicker) {
            const hasMultipleAddresses = state.addresses.length > 1;
            addressPicker.hidden = !hasMultipleAddresses;
            addressPicker.classList.toggle("d-none", !hasMultipleAddresses);
        }
        select.innerHTML = (state.addresses.length ? '<option value="">เลือกที่อยู่จัดส่ง</option>' : '<option value="">ลูกค้านี้ยังไม่มีที่อยู่จัดส่ง</option>') + state.addresses.map((address) => `<option value="${address.id}">${escapeHtml(addressLabel(address))}</option>`).join("");
        select.disabled = state.addresses.length === 0;
        const preferred = preferredAddressId && state.addresses.find((address) => String(address.id) === String(preferredAddressId));
        const defaultAddress = state.addresses.find((address) => address.is_default === true || address.is_default === 1 || address.is_default === "1");
        const onlyAddress = state.addresses.length === 1 ? state.addresses[0] : null;
        const selected = preferred || defaultAddress || onlyAddress;
        if (selected) {
            select.value = String(selected.id);
            applyAddressSelection(selected.id);
        } else {
            select.value = "";
            refreshPricingContext();
            render();
        }
    }

    async function setCustomer(customerId, preferredAddressId = null) { await loadAddresses(customerId, preferredAddressId); }
    function setAddress(addressId) { $("#v3-address-id").value = addressId ? String(addressId) : ""; $("#v3-address-id").dispatchEvent(new Event("change")); }
    function setDeliveryType(deliveryType) {
        const next = deliveryType === "pickup" ? "pickup" : "delivery";
        state.deliveryType = next;
        const candidateZone = state.address?.delivery_zone || null;
        state.zone = next === "delivery" && zoneIsActive(candidateZone) ? candidateZone : null;
        if (next === "pickup") state.deliveryFee = 0;
        state.deliveryFeeEdited = false;
        $("#v3-pickup").checked = next === "pickup";
        refreshPricingContext();
        render();
    }
    function canConfirmDelivery() {
        if (state.deliveryType !== "delivery") return true;
        if (state.addressLoading) { window.FinalPos?.showFeedback("กรุณารอให้โหลดที่อยู่และโซนเสร็จก่อน", "error"); return false; }
        if (state.zone?.id && !zoneIsActive(state.zone)) { window.FinalPos?.showFeedback("ที่อยู่จัดส่งนี้ไม่มีโซนจัดส่งที่ใช้งานอยู่", "error"); return false; }
        if (!deliveryDateIsValid()) { window.FinalPos?.showFeedback("กรุณากรอกวันที่จัดส่งให้ถูกต้องก่อนยืนยัน", "error"); return false; }
        if (!state.customerId) { window.FinalPos?.showFeedback("กรุณาเลือกลูกค้าก่อนยืนยันการจัดส่ง", "error"); return false; }
        if (!state.addressId || !state.address) { window.FinalPos?.showFeedback("กรุณาเลือกที่อยู่จัดส่งก่อนยืนยันการจัดส่ง", "error"); return false; }
        if (!state.zone?.id) { window.FinalPos?.showFeedback("ที่อยู่จัดส่งนี้ยังไม่มีโซนจัดส่ง กรุณาเลือกที่อยู่หรือกำหนดโซนก่อนยืนยัน", "error"); return false; }
        return true;
    }
    function buildPayload(payment) { return { hold_bill_id: state.holdBillId, customer_id: state.customerId || null, customer_delivery_address_id: state.addressId || null, technician_id: $("#v3-technician-id").value || null, delivery_date: state.deliveryType === "delivery" ? (deliveryDateField()?.value || null) : null, delivery_type: state.deliveryType, delivery_fee: state.deliveryFee.toFixed(2), discount: state.discount.toFixed(2), notes: state.note || null, items: state.cart.map((item) => ({ product_id: item.productId, product_unit_id: item.productUnitId, qty: item.qty, selling_price: Number(item.price).toFixed(2), price_was_edited: Boolean(item.priceWasEdited), price_changed_since_hold: Boolean(item.priceChangedSinceHold) })), ...payment }; }

    function sanitizeZoneOptions() {
        const select = $("#v3-price-zone-select");
        if (!select) return;
        select.querySelectorAll("option").forEach((option) => {
            if (!option.dataset.zone) return;
            let zone = null;
            try {
                zone = JSON.parse(option.dataset.zone);
            } catch {
                zone = null;
            }
            const active = zoneIsActive(zone);
            option.disabled = !active;
            option.hidden = !active;
        });
    }

    function init() {
        sanitizeZoneOptions(); refreshPricingContext(); render(); filterProducts();
        $("#v3-address-id").addEventListener("change", (event) => { if (!applyAddressSelection(event.target.value || "")) event.target.value = state.addressId || ""; });
        $("#v3-pickup").addEventListener("change", () => setDeliveryType($("#v3-pickup").checked ? "pickup" : "delivery"));
        $("#v3-product-search").addEventListener("input", filterProducts); $("#v3-stock-only").addEventListener("change", filterProducts); $("#v3-customer-id").addEventListener("change", loadAddresses);
        $("#v3-delivery-date-display")?.addEventListener("input", (event) => { const iso = window.PosDate?.toIso(event.target.value); const help = $("#v3-delivery-date-help"); if (iso) { $("#v3-delivery-date").value = iso; event.target.classList.remove("is-invalid"); if (help) help.textContent = `วันที่จัดส่ง: ${window.PosDate.formatDisplay(iso)}`; } else { $("#v3-delivery-date").value = ""; event.target.classList.add("is-invalid"); if (help) help.textContent = "กรุณากรอกวันที่เป็น วว/ดด/ปปปป"; } });
        $("#v3-discount").addEventListener("input", (e) => { state.discount = Math.max(0, Number(e.target.value) || 0); render(); }); $("#v3-delivery-fee").addEventListener("input", (e) => { if ($("#v3-pickup").checked) { state.deliveryFee = 0; e.target.value = "0.00"; } else { state.deliveryFee = Math.max(0, Number(e.target.value) || 0); state.deliveryFeeEdited = true; } $("#v3-total").textContent = money(total()); });
        $("#v3-delivery").addEventListener("click", () => setDeliveryType("delivery")); $("#v3-pickup-button").addEventListener("click", () => setDeliveryType("pickup"));
        document.querySelectorAll(".v3-category").forEach((button) => button.addEventListener("click", () => { document.querySelectorAll(".v3-category").forEach((b) => b.classList.remove("active")); button.classList.add("active"); state.category = button.dataset.category; filterProducts(); }));
        document.querySelectorAll(".v3-filter").forEach((button) => button.addEventListener("click", () => { document.querySelectorAll(".v3-filter").forEach((b) => b.classList.remove("active")); button.classList.add("active"); state.filter = button.dataset.filter; filterProducts(); }));
        document.querySelectorAll(".v3-product-card").forEach((card) => card.addEventListener("click", () => openQuantity(JSON.parse(card.dataset.product)))); $("#v3-quantity-confirm").addEventListener("click", confirmQuantity);
        $("#v3-quantity-input").addEventListener("input", syncQuantityPreview); $("#v3-quantity-decrease")?.addEventListener("click", () => { const input = $("#v3-quantity-input"); input.value = Math.max(0, Number(input.value || 0) - 1); syncQuantityPreview(); input.focus(); }); $("#v3-quantity-increase")?.addEventListener("click", () => { const input = $("#v3-quantity-input"); const active = state.activeProduct; const unit = active ? unitFor(active.product, active.unitId) : null; input.value = Math.min(active ? availableSaleQuantity(active.product, unit) : 0, Number(input.value || 0) + 1); syncQuantityPreview(); input.focus(); }); $("#v3-quantity-input").addEventListener("keydown", (event) => { if (event.key === "Enter") { event.preventDefault(); confirmQuantity(); } if (event.key === "Escape") window.jQuery($("#v3-quantity-modal")).modal("hide"); });
        $("#v3-cart-items").addEventListener("click", (event) => { const row = event.target.closest(".v3-cart-row"); if (!row || event.target.dataset.action !== "remove") return; state.cart.splice(Number(row.dataset.index), 1); render(); });
        $("#v3-cart-items").addEventListener("input", (event) => { if (!event.target.matches(".v3-cart-quantity")) return; const index = Number(event.target.dataset.index); const item = state.cart[index]; const qty = Number(event.target.value); if (!item || !Number.isFinite(qty) || qty <= 0 || cartBaseStock(item.product, index) + requiredBaseStock(qty, item.unit) > Number(item.product.stock_qty) + 0.00005) return; item.qty = qty; const systemPrice = unitPrice(item.unit, qty, item.product); if (item.priceWasEdited) item.originalPrice = systemPrice; else item.price = systemPrice; const row = event.target.closest(".v3-cart-row"); const priceCell = row.querySelector(".v3-cart-unit-price"); if (priceCell?.tagName === "INPUT") priceCell.value = Number(item.price).toFixed(2); else if (priceCell) priceCell.textContent = money(item.price); row.querySelector(".v3-line-total").textContent = money(item.qty * item.price); syncCartTotals(); });
        $("#v3-cart-items").addEventListener("input", (event) => { if (!event.target.matches(".v3-cart-unit-price")) return; const index = Number(event.target.dataset.index); const item = state.cart[index]; if (!item || !commitUnitPrice(index, event.target.value)) return; const row = event.target.closest(".v3-cart-row"); row.querySelector(".v3-line-total").textContent = money(item.qty * item.price); syncCartTotals(); });
        $("#v3-cart-items").addEventListener("keydown", (event) => { if (!event.target.matches(".v3-cart-unit-price") || event.key !== "Enter") return; event.preventDefault(); const index = Number(event.target.dataset.index); commitUnitPrice(index, event.target.value); render(); });
        $("#v3-cart-items").addEventListener("change", (event) => { if (event.target.matches(".v3-cart-quantity")) { render(); return; } if (!event.target.matches(".v3-cart-unit-price")) return; const index = Number(event.target.dataset.index); const item = state.cart[index]; if (!item || !commitUnitPrice(index, event.target.value)) { render(); return; } render(); });
        const openNoteEditor = () => { $("#v3-note-input").value = state.note; window.jQuery($("#v3-note-modal")).modal("show"); }; $("#v3-note-button").addEventListener("click", openNoteEditor); const noteStatus = $("#v3-note-status"); noteStatus?.addEventListener("click", () => { if (state.note) openNoteEditor(); }); noteStatus?.addEventListener("keydown", (event) => { if (state.note && (event.key === "Enter" || event.key === " ")) { event.preventDefault(); openNoteEditor(); } }); $("#v3-note-confirm").addEventListener("click", () => { state.note = $("#v3-note-input").value.trim(); syncNoteUi(); window.jQuery($("#v3-note-modal")).modal("hide"); }); $("#v3-new-bill").addEventListener("click", () => { if (!state.cart.length || confirm("ล้างตะกร้าและเริ่มบิลใหม่หรือไม่?")) { if (window.FinalPos?.resetSale) window.FinalPos.resetSale(); else { state.cart = []; state.discount = 0; state.note = ""; state.deliveryFee = 0; state.deliveryFeeEdited = false; state.holdBillId = null; resetDeliveryDate(); $("#v3-discount").value = "0.00"; render(); } $("#v3-product-search").focus(); } }); $("#v3-clear-cart").addEventListener("click", () => $("#v3-new-bill").click());
        const payment = PosPayment.createController({ getTotal: () => money(total()), onConfirm: submit }); window.FinalPos?.configure({ payment, state, total, render, loadAddresses, root, customerSelect: $("#v3-customer-id"), addressSelect: $("#v3-address-id"), setCustomer, setAddress, setDeliveryType, canConfirmDelivery }); $("#v3-submit").addEventListener("click", () => { if (!state.cart.length) return alert("กรุณาเพิ่มสินค้าอย่างน้อย 1 รายการ"); window.FinalPos?.openConfirmation(); if (!window.FinalPos) payment.open(); });
        $("#v3-product-search").addEventListener("keydown", (event) => { if (event.key !== "Enter") return; const term = event.target.value.trim().toLowerCase(); const card = [...document.querySelectorAll(".v3-product-card")].find((candidate) => { const p = JSON.parse(candidate.dataset.product); return String(p.barcode || "").toLowerCase() === term || p.productUnits?.some((u) => u.barcodes?.some((b) => String(b.barcode).toLowerCase() === term)); }); if (card) { openQuantity(JSON.parse(card.dataset.product)); event.target.value = ""; } });
        document.addEventListener("keydown", (event) => { if (event.key === "F2" || event.key === "F8") { event.preventDefault(); $("#v3-product-search").focus(); $("#v3-product-search").select(); } if (event.key === "F9") { event.preventDefault(); $("#v3-submit").click(); } if (event.key === "Escape" && !document.querySelector(".modal.show")) $("#v3-product-search").focus(); }); setInterval(() => { const clock = $("#pos-v3-clock"); if (clock) clock.textContent = new Date().toLocaleTimeString("th-TH", { hour: "2-digit", minute: "2-digit" }); }, 1000);
    }

    async function submit(payment) {
        if (!canConfirmDelivery()) return;
        const guard = window.SaleIntentStorage.createSubmissionGuard(); if (!guard.start()) return; const payload = buildPayload(window.PosPayment.payload(payment)); const pending = window.SaleIntentStorage.createManager({ storageKey: "atrilak.pos.v3.pending-sale.v1" }); let intent = null;
        try { intent = await pending.keyFor(payload); payload.idempotency_key = intent.key; const response = await fetch(root.dataset.storeUrl, { method: "POST", headers: { "Content-Type": "application/json", Accept: "application/json", "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify(payload) }); const data = await response.json(); if (!response.ok || !data.success) throw Object.assign(new Error(data.message || "บันทึกการขายไม่สำเร็จ"), { status: response.status }); pending.clear(intent.key); state.cart = []; render(); window.FinalPos?.showSuccess(data); } catch (error) { if (intent && window.SaleIntentStorage.isDefinitiveClientError(error.status)) pending.clear(intent.key); throw error; } finally { guard.release(); }
    }

    init();
})();
