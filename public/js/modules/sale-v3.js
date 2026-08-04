(function () {
    const root = document.getElementById("pos-v3");
    if (!root) return;

    const state = { cart: [], customerId: "", addressId: "", deliveryType: "pickup", address: null, zone: null, deliveryFee: 0, deliveryFeeEdited: false, discount: 0, note: "", holdBillId: null, activeProduct: null, editIndex: -1, filter: "all", category: "" };
    const $ = (selector) => document.querySelector(selector);
    const money = (value) => Number(value || 0).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const escapeHtml = (value) => String(value ?? "").replace(/[&<>'"]/g, (char) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "'": "&#39;", '"': "&quot;" }[char]));
    const unitFor = (product, id = null) => product.productUnits?.find((unit) => String(unit.id) === String(id)) || product.productUnits?.find((unit) => unit.is_sale_unit) || product.productUnits?.[0] || { id: null, selling_price: product.price, unit: { name: product.unit || "หน่วย" }, barcodes: [] };
    const roundPrice = (value, product) => { const unit = Number(product.rounding_unit || 5); const direction = product.rounding_direction || "up"; const quotient = value / unit; const rounded = direction === "down" ? Math.floor(quotient) : direction === "nearest" ? Math.round(quotient) : Math.ceil(quotient); return rounded * unit; };
    const unitPrice = (unit, qty, product) => { const tierPrice = (unit.price_tiers || []).reduce((price, tier) => Number(qty) >= Number(tier.min_qty) ? (tier.fixed_price !== null && tier.fixed_price !== "" ? Number(tier.fixed_price) : Number(unit.selling_price) * (1 - Number(tier.discount_percent || 0) / 100)) : price, Number(unit.selling_price || product.price || 0)); const delivery = state.zone && state.zone.active && state.deliveryType === "delivery"; const roundingIncrement = product.category_rounding_override || state.zone?.rounding_increment || "0.25"; if (delivery && window.ZonePricingMath) return Number(window.ZonePricingMath.ceilAfterMarkup(tierPrice, state.zone.price_markup_percent || 0, roundingIncrement)); const markup = delivery ? Number(state.zone.price_markup_percent || 0) : 0; return roundPrice(tierPrice * (1 + markup / 100), product); };
    const total = () => state.cart.reduce((sum, item) => sum + Number(item.qty) * Number(item.price), 0) + state.deliveryFee - state.discount;
    function updateDeliveryPreview() { if (state.deliveryType === "pickup") { state.deliveryFee = 0; return; } if (state.deliveryFeeEdited) return; if (!state.zone) { state.deliveryFee = 0; return; } const profit = state.cart.reduce((sum, item) => sum + Number(item.qty) * (Number(item.price) - Number(item.product.cost_price || 0) * Number(item.unit.conversion_rate || 1)), 0) - state.discount; state.deliveryFee = Math.max(0, Number(state.zone.minimum_profit || 0) - profit); }
    function syncCartTotals() { updateDeliveryPreview(); $("#v3-cart-count").textContent = state.cart.length; $("#v3-delivery-fee").value = money(state.deliveryFee); $("#v3-subtotal").textContent = money(state.cart.reduce((sum, item) => sum + item.qty * item.price, 0)); $("#v3-total").textContent = money(total()); }

    function add(product, unitId = null, qty = 1) {
        const unit = unitFor(product, unitId);
        const key = `${product.id}:${unit.id || "base"}`;
        const existing = state.cart.find((item) => item.key === key);
        if (existing) { const wasEdited = Boolean(existing.priceWasEdited); const salePrice = existing.price; existing.qty += Number(qty); const systemPrice = unitPrice(unit, existing.qty, product); existing.originalPrice = wasEdited ? systemPrice : null; existing.price = wasEdited ? salePrice : systemPrice; }
        else state.cart.push({ key, product, productId: product.id, productUnitId: unit.id, unit, unitName: unit.unit?.name || product.unit || "หน่วย", name: product.name, qty: Number(qty), price: unitPrice(unit, qty, product) });
        render();
    }

    function repriceCart() { state.cart.forEach((item) => { const systemPrice = unitPrice(item.unit, item.qty, item.product); if (item.priceWasEdited) item.originalPrice = systemPrice; else item.price = systemPrice; }); }

    function ensurePriceMetadata() { state.cart.forEach((item) => { if (!Object.prototype.hasOwnProperty.call(item, "priceWasEdited")) item.priceWasEdited = false; if (!Object.prototype.hasOwnProperty.call(item, "originalPrice")) item.originalPrice = null; if (!Object.prototype.hasOwnProperty.call(item, "priceChangedSinceHold")) item.priceChangedSinceHold = false; }); }

    function refreshPricingContext() {
        repriceCart();
        document.querySelectorAll(".v3-product-card").forEach((card) => { const product = JSON.parse(card.dataset.product); const label = card.querySelector(".v3-product-price"); if (label) label.textContent = `${money(unitPrice(unitFor(product), 1, product))} บาท`; });
        const zoneLabel = $("#v3-address-zone"); if (zoneLabel) zoneLabel.textContent = state.zone?.name ? `โซนจัดส่ง: ${state.zone.name} +${money(state.zone.price_markup_percent || 0)}%` : "โซนจัดส่ง: -";
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
        $("#v3-customer-address")?.classList.toggle("d-none", pickup);
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
            row.querySelector('[data-action="edit"]')?.remove();
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

    function render() {
        ensurePriceMetadata();
        syncFulfillmentUi();
        updateDeliveryPreview();
        const container = $("#v3-cart-items");
        if (!state.cart.length) container.innerHTML = '<div class="pos-v3-empty">ยังไม่มีสินค้า<br><small>คลิกสินค้า หรือยิง Barcode เพื่อเริ่มขาย</small></div>';
        else container.innerHTML = state.cart.map((item, index) => `<div class="v3-cart-row" data-index="${index}"><input class="v3-cart-quantity" data-index="${index}" value="${item.qty}" inputmode="decimal" aria-label="จำนวน ${escapeHtml(item.name)}"><div class="v3-cart-product"><strong>${escapeHtml(item.name)}</strong><small>${escapeHtml(item.unitName)}</small></div><strong class="v3-cart-unit-price">${money(item.price)}</strong><strong class="v3-line-total">${money(item.qty * item.price)}</strong><button class="v3-cart-remove" data-action="remove" title="ลบ ${escapeHtml(item.name)}" aria-label="ลบ ${escapeHtml(item.name)}">×</button></div>`).join("");
        container.querySelectorAll(".v3-cart-row").forEach((row) => { const actions = document.createElement("span"); actions.className = "v3-cart-actions"; const edit = document.createElement("button"); edit.type = "button"; edit.className = "btn btn-link btn-sm"; edit.dataset.action = "edit"; edit.textContent = "✎"; edit.title = "แก้ราคา"; const restore = document.createElement("button"); restore.type = "button"; restore.className = "btn btn-link btn-sm"; restore.dataset.action = "restore"; restore.textContent = "↺"; restore.title = "คืนราคาปกติ"; actions.append(edit, restore); row.append(actions); });
        syncInlinePriceInputs();
        $("#v3-subtotal").textContent = money(state.cart.reduce((sum, item) => sum + item.qty * item.price, 0));
        $("#v3-cart-count").textContent = state.cart.length;
        $("#v3-delivery-fee").value = money(state.deliveryFee);
        $("#v3-total").textContent = money(total());
        const selectedAddress = state.address;
        const zoneName = state.zone?.name || "";
        const addressText = selectedAddress?.textContent?.trim() || "เลือกที่อยู่จัดส่งเพื่อเริ่มคำนวณโซน";
        const fulfillmentText = state.deliveryType === "pickup" ? "รับเอง · ค่าส่ง 0.00 บาท" : `จัดส่ง · ค่าส่ง ${money(state.deliveryFee)} บาท`;
        $("#v3-customer-address").innerHTML = `<i class="fas fa-map-marker-alt"></i> ${escapeHtml(addressText)}${zoneName ? ` <span class="badge badge-success ml-2">โซน: ${escapeHtml(zoneName)}</span>` : ""} <span class="text-muted ml-2">${fulfillmentText}</span>`;
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

    function openQuantity(product, unitId = null, existingIndex = -1) {
        state.activeProduct = { product, unitId, existingIndex };
        $("#v3-quantity-title").textContent = product.name;
        $("#v3-quantity-stock").textContent = money(product.stock_qty);
        $("#v3-quantity-input").value = existingIndex >= 0 ? state.cart[existingIndex].qty : "1";
        $("#v3-quantity-error").textContent = "";
        $("#v3-quantity-modal").dataset.returnFocus = document.activeElement?.id || "v3-product-search";
        window.jQuery($("#v3-quantity-modal")).one("shown.bs.modal", () => { $("#v3-quantity-input").focus(); $("#v3-quantity-input").select(); });
        window.jQuery($("#v3-quantity-modal")).one("hidden.bs.modal", () => $("#" + $("#v3-quantity-modal").dataset.returnFocus)?.focus());
        window.jQuery($("#v3-quantity-modal")).modal("show");
    }

    function confirmQuantity() {
        const qty = Number($("#v3-quantity-input").value);
        if (!Number.isFinite(qty) || qty < 0) { $("#v3-quantity-error").textContent = "จำนวนไม่ถูกต้อง"; return; }
        const { product, unitId, existingIndex } = state.activeProduct;
        if (qty > Number(product.stock_qty)) { $("#v3-quantity-error").textContent = `สต็อกคงเหลือ ${money(product.stock_qty)}`; return; }
        if (existingIndex >= 0) { if (qty === 0) state.cart.splice(existingIndex, 1); else { state.cart[existingIndex].qty = qty; const currentItem = state.cart[existingIndex]; currentItem.qty = qty; const systemPrice = unitPrice(currentItem.unit, qty, currentItem.product); if (currentItem.priceWasEdited) currentItem.originalPrice = systemPrice; else currentItem.price = systemPrice; } }
        else if (qty > 0) add(product, unitId, qty);
        render(); window.jQuery($("#v3-quantity-modal")).modal("hide");
    }

    function openEdit(index) {
        const item = state.cart[index]; state.editIndex = index;
        $("#v3-edit-title").textContent = item.name;
        $("#v3-edit-unit").innerHTML = (item.product.productUnits || [{ id: null, unit: { name: item.product.unit || "หน่วย" }, selling_price: item.product.price }]).map((unit) => `<option value="${unit.id || ""}" ${String(unit.id) === String(item.productUnitId) ? "selected" : ""}>${escapeHtml(unit.unit?.name || item.product.unit || "หน่วย")}</option>`).join("");
        $("#v3-edit-qty").value = item.qty; $("#v3-edit-price").value = item.price; $("#v3-edit-error").textContent = "";
        window.jQuery($("#v3-edit-modal")).one("shown.bs.modal", () => $("#v3-edit-qty").focus()); window.jQuery($("#v3-edit-modal")).modal("show");
    }

    function confirmEdit() {
        const item = state.cart[state.editIndex]; const qty = Number($("#v3-edit-qty").value); const price = Number($("#v3-edit-price").value); const unit = unitFor(item.product, $("#v3-edit-unit").value || null);
        if (!item || !Number.isFinite(qty) || qty <= 0 || !Number.isFinite(price) || price <= 0) { $("#v3-edit-error").textContent = "จำนวนและราคาต้องมากกว่า 0"; return; }
        if (qty > Number(item.product.stock_qty)) { $("#v3-edit-error").textContent = `สต็อกคงเหลือ ${money(item.product.stock_qty)}`; return; }
        item.productUnitId = unit.id; item.unit = unit; item.unitName = unit.unit?.name || item.product.unit || "หน่วย"; item.qty = qty; item.price = unitPrice(unit, qty, item.product); item.key = `${item.productId}:${unit.id || "base"}`; render(); window.jQuery($("#v3-edit-modal")).modal("hide");
    }

    function confirmEditWithPriceIntent() {
        const item = state.cart[state.editIndex];
        const qty = Number($("#v3-edit-qty").value);
        const requestedPrice = Number($("#v3-edit-price").value);
        const unit = item ? unitFor(item.product, $("#v3-edit-unit").value || null) : null;
        if (!item || !Number.isFinite(qty) || qty <= 0 || !Number.isFinite(requestedPrice) || requestedPrice <= 0) {
            $("#v3-edit-error").textContent = "Invalid quantity or price";
            return;
        }
        if (qty > Number(item.product.stock_qty)) {
            $("#v3-edit-error").textContent = `Stock remaining ${money(item.product.stock_qty)}`;
            return;
        }
        const previousPrice = Number(item.price);
        const previousUnitId = item.productUnitId;
        const previousOverride = Boolean(item.priceWasEdited);
        const previousHoldChange = Boolean(item.priceChangedSinceHold);
        const changed = String(previousUnitId) !== String(unit.id)
            || Math.abs(requestedPrice - previousPrice) > 0.005;
        const systemPrice = unitPrice(unit, qty, item.product);
        item.productUnitId = unit.id;
        item.unit = unit;
        item.unitName = unit.unit?.name || item.product.unit || "หน่วย";
        item.qty = qty;
        item.key = `${item.productId}:${unit.id || "base"}`;
        if (changed) {
            item.price = requestedPrice;
            item.priceWasEdited = true;
            item.originalPrice = systemPrice;
            item.priceChangedSinceHold = Boolean(previousHoldChange || state.holdBillId);
        } else if (previousOverride) {
            item.price = previousPrice;
            item.priceWasEdited = true;
            item.originalPrice = systemPrice;
            item.priceChangedSinceHold = previousHoldChange;
        } else {
            item.price = systemPrice;
            item.priceWasEdited = false;
            item.originalPrice = null;
            item.priceChangedSinceHold = false;
        }
        render();
        window.jQuery($("#v3-edit-modal")).modal("hide");
    }

    async function loadAddresses(customerId = null, preferredAddressId = null) {
        if (customerId?.target) customerId = null;
        const id = customerId === null ? $("#v3-customer-id").value : String(customerId || ""); const select = $("#v3-address-id"); state.customerId = id; $("#v3-customer-id").value = id; select.innerHTML = id ? '<option>กำลังโหลด...</option>' : '<option value="">เลือกลูกค้าก่อน</option>'; select.disabled = !id; state.addressId = ""; state.address = null; state.zone = null; state.deliveryFee = 0;
        if (!id) { render(); return; }
        const response = await fetch(root.dataset.addressUrlTemplate.replace("__CUSTOMER__", id), { headers: { Accept: "application/json" } }); const addresses = await response.json(); select.innerHTML = '<option value="">เลือกที่อยู่จัดส่ง</option>' + addresses.map((a) => `<option value="${a.id}" data-zone='${escapeHtml(JSON.stringify(a.delivery_zone || {}))}'>${escapeHtml(a.label || a.address || a.name || "ที่อยู่ "+a.id)}</option>`).join("");
        const primary = (preferredAddressId && addresses.find((a) => String(a.id) === String(preferredAddressId))) || addresses.find((a) => a.is_default) || addresses[0]; if (primary) { select.value = primary.id; select.dispatchEvent(new Event("change")); }
    }

    async function setCustomer(customerId, preferredAddressId = null) { await loadAddresses(customerId, preferredAddressId); }
    function setAddress(addressId) { $("#v3-address-id").value = addressId ? String(addressId) : ""; $("#v3-address-id").dispatchEvent(new Event("change")); }
    function setDeliveryType(deliveryType) { state.deliveryType = deliveryType === "pickup" ? "pickup" : "delivery"; $("#v3-pickup").checked = state.deliveryType === "pickup"; $("#v3-pickup").dispatchEvent(new Event("change")); }
    function buildPayload(payment) { return { hold_bill_id: state.holdBillId, customer_id: state.customerId || null, customer_delivery_address_id: state.addressId || null, technician_id: $("#v3-technician-id").value || null, sale_date: $("#v3-sale-date").value, delivery_type: state.deliveryType, delivery_fee: state.deliveryFee.toFixed(2), discount: state.discount.toFixed(2), notes: state.note || null, items: state.cart.map((item) => ({ product_id: item.productId, product_unit_id: item.productUnitId, qty: item.qty, selling_price: Number(item.price).toFixed(2), price_was_edited: Boolean(item.priceWasEdited), price_changed_since_hold: Boolean(item.priceChangedSinceHold) })), ...payment }; }

    function init() {
        render(); filterProducts();
        $("#v3-address-id").addEventListener("change", (event) => { state.addressId = event.target.value || ""; });
        $("#v3-pickup").addEventListener("change", () => { state.deliveryType = $("#v3-pickup").checked ? "pickup" : "delivery"; });
        $("#v3-product-search").addEventListener("input", filterProducts); $("#v3-stock-only").addEventListener("change", filterProducts); $("#v3-customer-id").addEventListener("change", loadAddresses);
        $("#v3-address-id").addEventListener("change", (e) => { const option = e.target.selectedOptions[0]; state.address = option; try { state.zone = JSON.parse(option?.dataset.zone || "{}"); } catch (_) { state.zone = null; } state.deliveryFee = 0; state.deliveryFeeEdited = false; refreshPricingContext(); render(); }); $("#v3-pickup").addEventListener("change", () => { state.deliveryFee = 0; state.deliveryFeeEdited = false; refreshPricingContext(); render(); }); $("#v3-discount").addEventListener("input", (e) => { state.discount = Math.max(0, Number(e.target.value) || 0); render(); }); $("#v3-delivery-fee").addEventListener("input", (e) => { if ($("#v3-pickup").checked) { state.deliveryFee = 0; e.target.value = "0.00"; } else { state.deliveryFee = Math.max(0, Number(e.target.value) || 0); state.deliveryFeeEdited = true; } $("#v3-total").textContent = money(total()); });
        $("#v3-delivery").addEventListener("click", () => { $("#v3-pickup").checked = false; $("#v3-pickup").dispatchEvent(new Event("change")); }); $("#v3-pickup-button").addEventListener("click", () => { $("#v3-pickup").checked = true; $("#v3-pickup").dispatchEvent(new Event("change")); });
        document.querySelectorAll(".v3-category").forEach((button) => button.addEventListener("click", () => { document.querySelectorAll(".v3-category").forEach((b) => b.classList.remove("active")); button.classList.add("active"); state.category = button.dataset.category; filterProducts(); }));
        document.querySelectorAll(".v3-filter").forEach((button) => button.addEventListener("click", () => { document.querySelectorAll(".v3-filter").forEach((b) => b.classList.remove("active")); button.classList.add("active"); state.filter = button.dataset.filter; filterProducts(); }));
        document.querySelectorAll(".v3-product-card").forEach((card) => card.addEventListener("click", () => openQuantity(JSON.parse(card.dataset.product)))); $("#v3-quantity-confirm").addEventListener("click", confirmQuantity); $("#v3-edit-confirm").addEventListener("click", confirmEditWithPriceIntent);
        $("#v3-quantity-input").addEventListener("keydown", (event) => { if (event.key === "Enter") { event.preventDefault(); confirmQuantity(); } if (event.key === "Escape") window.jQuery($("#v3-quantity-modal")).modal("hide"); });
        $("#v3-edit-qty").addEventListener("keydown", (event) => { if (event.key === "Enter") { event.preventDefault(); confirmEditWithPriceIntent(); } if (event.key === "Escape") window.jQuery($("#v3-edit-modal")).modal("hide"); });
        $("#v3-cart-items").addEventListener("click", (event) => { const row = event.target.closest(".v3-cart-row"); if (!row || event.target.dataset.action !== "remove") return; state.cart.splice(Number(row.dataset.index), 1); render(); });
        $("#v3-cart-items").addEventListener("click", (event) => { const action = event.target.dataset.action; const row = event.target.closest(".v3-cart-row"); if (!row || action !== "restore") return; const index = Number(row.dataset.index); const item = state.cart[index]; if (!item) return; const systemPrice = unitPrice(item.unit, item.qty, item.product); item.price = systemPrice; item.originalPrice = null; item.priceWasEdited = false; item.priceChangedSinceHold = Boolean(item.priceChangedSinceHold && state.holdBillId); render(); });
        $("#v3-cart-items").addEventListener("input", (event) => { if (!event.target.matches(".v3-cart-quantity")) return; const index = Number(event.target.dataset.index); const item = state.cart[index]; const qty = Number(event.target.value); if (!item || !Number.isFinite(qty) || qty <= 0 || qty > Number(item.product.stock_qty)) return; item.qty = qty; const systemPrice = unitPrice(item.unit, qty, item.product); if (item.priceWasEdited) item.originalPrice = systemPrice; else item.price = systemPrice; const row = event.target.closest(".v3-cart-row"); const priceCell = row.querySelector(".v3-cart-unit-price"); if (priceCell?.tagName === "INPUT") priceCell.value = Number(item.price).toFixed(2); else if (priceCell) priceCell.textContent = money(item.price); row.querySelector(".v3-line-total").textContent = money(item.qty * item.price); syncCartTotals(); });
        $("#v3-cart-items").addEventListener("input", (event) => { if (!event.target.matches(".v3-cart-unit-price")) return; const index = Number(event.target.dataset.index); const item = state.cart[index]; if (!item || !commitUnitPrice(index, event.target.value)) return; const row = event.target.closest(".v3-cart-row"); row.querySelector(".v3-line-total").textContent = money(item.qty * item.price); syncCartTotals(); });
        $("#v3-cart-items").addEventListener("keydown", (event) => { if (!event.target.matches(".v3-cart-unit-price") || event.key !== "Enter") return; event.preventDefault(); const index = Number(event.target.dataset.index); commitUnitPrice(index, event.target.value); render(); });
        $("#v3-cart-items").addEventListener("change", (event) => { if (event.target.matches(".v3-cart-quantity")) { render(); return; } if (!event.target.matches(".v3-cart-unit-price")) return; const index = Number(event.target.dataset.index); const item = state.cart[index]; if (!item || !commitUnitPrice(index, event.target.value)) { render(); return; } render(); });
        $("#v3-note-button").addEventListener("click", () => { $("#v3-note-input").value = state.note; window.jQuery($("#v3-note-modal")).modal("show"); }); $("#v3-note-confirm").addEventListener("click", () => { state.note = $("#v3-note-input").value.trim(); window.jQuery($("#v3-note-modal")).modal("hide"); }); $("#v3-new-bill").addEventListener("click", () => { if (!state.cart.length || confirm("ล้างตะกร้าและเริ่มบิลใหม่หรือไม่?")) { state.cart = []; state.discount = 0; state.note = ""; state.deliveryFee = 0; state.deliveryFeeEdited = false; state.holdBillId = null; $("#v3-discount").value = "0.00"; render(); $("#v3-product-search").focus(); } }); $("#v3-clear-cart").addEventListener("click", () => $("#v3-new-bill").click());
        const payment = PosPayment.createController({ getTotal: () => money(total()), onConfirm: submit }); window.FinalPos?.configure({ payment, state, total, render, loadAddresses, root, customerSelect: $("#v3-customer-id"), addressSelect: $("#v3-address-id") }); $("#v3-submit").addEventListener("click", () => { if (!state.cart.length) return alert("กรุณาเพิ่มสินค้าอย่างน้อย 1 รายการ"); window.FinalPos?.openConfirmation(); if (!window.FinalPos) payment.open(); });
        $("#v3-product-search").addEventListener("keydown", (event) => { if (event.key !== "Enter") return; const term = event.target.value.trim().toLowerCase(); const card = [...document.querySelectorAll(".v3-product-card")].find((candidate) => { const p = JSON.parse(candidate.dataset.product); return String(p.barcode || "").toLowerCase() === term || p.productUnits?.some((u) => u.barcodes?.some((b) => String(b.barcode).toLowerCase() === term)); }); if (card) { openQuantity(JSON.parse(card.dataset.product)); event.target.value = ""; } });
        document.addEventListener("keydown", (event) => { if (event.key === "F2" || event.key === "F8") { event.preventDefault(); $("#v3-product-search").focus(); $("#v3-product-search").select(); } if (event.key === "F9") { event.preventDefault(); $("#v3-submit").click(); } if (event.key === "Escape" && !document.querySelector(".modal.show")) $("#v3-product-search").focus(); }); setInterval(() => { const clock = $("#pos-v3-clock"); if (clock) clock.textContent = new Date().toLocaleTimeString("th-TH", { hour: "2-digit", minute: "2-digit" }); }, 1000);
    }

    async function submit(payment) {
        const guard = window.SaleIntentStorage.createSubmissionGuard(); if (!guard.start()) return; const payload = buildPayload(window.PosPayment.payload(payment)); const pending = window.SaleIntentStorage.createManager({ storageKey: "atrilak.pos.v3.pending-sale.v1" }); let intent = null;
        try { intent = await pending.keyFor(payload); payload.idempotency_key = intent.key; const response = await fetch(root.dataset.storeUrl, { method: "POST", headers: { "Content-Type": "application/json", Accept: "application/json", "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify(payload) }); const data = await response.json(); if (!response.ok || !data.success) throw Object.assign(new Error(data.message || "บันทึกการขายไม่สำเร็จ"), { status: response.status }); pending.clear(intent.key); state.cart = []; render(); window.FinalPos?.showSuccess(data); } catch (error) { if (intent && window.SaleIntentStorage.isDefinitiveClientError(error.status)) pending.clear(intent.key); throw error; } finally { guard.release(); }
    }

    init();
})();
