(function () {
    const root = document.getElementById("pos-v3");
    if (!root) return;

    const state = { cart: [], address: null, deliveryFee: 0, discount: 0, note: "", activeProduct: null, editIndex: -1, filter: "all", category: "" };
    const $ = (selector) => document.querySelector(selector);
    const money = (value) => Number(value || 0).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const escapeHtml = (value) => String(value ?? "").replace(/[&<>'"]/g, (char) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "'": "&#39;", '"': "&quot;" }[char]));
    const unitFor = (product, id = null) => product.productUnits?.find((unit) => String(unit.id) === String(id)) || product.productUnits?.find((unit) => unit.is_sale_unit) || product.productUnits?.[0] || { id: null, selling_price: product.price, unit: { name: product.unit || "หน่วย" }, barcodes: [] };
    const unitPrice = (unit, qty) => (unit.price_tiers || []).reduce((price, tier) => Number(qty) >= Number(tier.min_qty) ? (tier.fixed_price !== null && tier.fixed_price !== "" ? Number(tier.fixed_price) : Number(unit.selling_price) * (1 - Number(tier.discount_percent || 0) / 100)) : price, Number(unit.selling_price || 0));
    const total = () => state.cart.reduce((sum, item) => sum + Number(item.qty) * Number(item.price), 0) + state.deliveryFee - state.discount;

    function add(product, unitId = null, qty = 1) {
        const unit = unitFor(product, unitId);
        const key = `${product.id}:${unit.id || "base"}`;
        const existing = state.cart.find((item) => item.key === key);
        if (existing) { existing.qty += Number(qty); existing.price = unitPrice(unit, existing.qty); }
        else state.cart.push({ key, product, productId: product.id, productUnitId: unit.id, unit, unitName: unit.unit?.name || product.unit || "หน่วย", name: product.name, qty: Number(qty), price: unitPrice(unit, qty) });
        render();
    }

    function render() {
        const container = $("#v3-cart-items");
        if (!state.cart.length) container.innerHTML = '<div class="pos-v3-empty">ยังไม่มีสินค้า<br><small>คลิกสินค้า หรือยิง Barcode เพื่อเริ่มขาย</small></div>';
        else container.innerHTML = state.cart.map((item, index) => `<div class="v3-cart-row" data-index="${index}"><div><strong>${escapeHtml(item.name)}</strong><small>${escapeHtml(item.unitName)} · ${money(item.price)}</small></div><div class="v3-cart-controls"><button data-action="minus">−</button><b>${item.qty}</b><button data-action="plus">+</button><button data-action="edit" title="แก้ไข">✎</button><button data-action="remove" title="ลบ">×</button></div><strong class="v3-line-total">${money(item.qty * item.price)}</strong></div>`).join("");
        $("#v3-subtotal").textContent = money(state.cart.reduce((sum, item) => sum + item.qty * item.price, 0));
        $("#v3-delivery-fee").textContent = money(state.deliveryFee);
        $("#v3-total").textContent = money(total());
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
        if (existingIndex >= 0) { if (qty === 0) state.cart.splice(existingIndex, 1); else { state.cart[existingIndex].qty = qty; state.cart[existingIndex].price = unitPrice(state.cart[existingIndex].unit, qty); } }
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
        item.productUnitId = unit.id; item.unit = unit; item.unitName = unit.unit?.name || item.product.unit || "หน่วย"; item.qty = qty; item.price = price; item.key = `${item.productId}:${unit.id || "base"}`; render(); window.jQuery($("#v3-edit-modal")).modal("hide");
    }

    async function loadAddresses() {
        const id = $("#v3-customer-id").value; const select = $("#v3-address-id"); select.innerHTML = id ? '<option>กำลังโหลด...</option>' : '<option value="">เลือกลูกค้าก่อน</option>'; select.disabled = !id; state.address = null; state.deliveryFee = 0;
        if (!id) { render(); return; }
        const response = await fetch(root.dataset.addressUrlTemplate.replace("__CUSTOMER__", id), { headers: { Accept: "application/json" } }); const addresses = await response.json(); select.innerHTML = '<option value="">เลือกที่อยู่จัดส่ง</option>' + addresses.map((a) => `<option value="${a.id}" data-fee="${a.delivery_zone?.base_delivery_fee || 0}">${escapeHtml(a.label || a.address || a.name || "ที่อยู่ "+a.id)}</option>`).join("");
        const primary = addresses.find((a) => a.is_default) || addresses[0]; if (primary) { select.value = primary.id; select.dispatchEvent(new Event("change")); }
    }

    function buildPayload(payment) { return { customer_id: $("#v3-customer-id").value || null, customer_delivery_address_id: $("#v3-address-id").value || null, technician_id: $("#v3-technician-id").value || null, sale_date: $("#v3-sale-date").value, delivery_type: $("#v3-pickup").checked ? "pickup" : "delivery", delivery_fee: state.deliveryFee.toFixed(2), discount: state.discount.toFixed(2), notes: state.note || null, items: state.cart.map((item) => ({ product_id: item.productId, product_unit_id: item.productUnitId, qty: item.qty, selling_price: Number(item.price).toFixed(2) })), ...payment }; }

    function init() {
        render(); filterProducts();
        $("#v3-product-search").addEventListener("input", filterProducts); $("#v3-stock-only").addEventListener("change", filterProducts); $("#v3-customer-id").addEventListener("change", loadAddresses);
        $("#v3-address-id").addEventListener("change", (e) => { const option = e.target.selectedOptions[0]; state.address = option; state.deliveryFee = $("#v3-pickup").checked ? 0 : Number(option?.dataset.fee || 0); render(); }); $("#v3-pickup").addEventListener("change", () => { state.deliveryFee = $("#v3-pickup").checked ? 0 : Number($("#v3-address-id").selectedOptions[0]?.dataset.fee || 0); render(); }); $("#v3-discount").addEventListener("input", (e) => { state.discount = Math.max(0, Number(e.target.value) || 0); render(); });
        document.querySelectorAll(".v3-category").forEach((button) => button.addEventListener("click", () => { document.querySelectorAll(".v3-category").forEach((b) => b.classList.remove("active")); button.classList.add("active"); state.category = button.dataset.category; filterProducts(); }));
        document.querySelectorAll(".v3-filter").forEach((button) => button.addEventListener("click", () => { document.querySelectorAll(".v3-filter").forEach((b) => b.classList.remove("active")); button.classList.add("active"); state.filter = button.dataset.filter; filterProducts(); }));
        document.querySelectorAll(".v3-product-card").forEach((card) => card.addEventListener("click", () => openQuantity(JSON.parse(card.dataset.product)))); $("#v3-quantity-confirm").addEventListener("click", confirmQuantity); $("#v3-edit-confirm").addEventListener("click", confirmEdit);
        $("#v3-quantity-input").addEventListener("keydown", (event) => { if (event.key === "Enter") { event.preventDefault(); confirmQuantity(); } if (event.key === "Escape") window.jQuery($("#v3-quantity-modal")).modal("hide"); });
        $("#v3-edit-qty").addEventListener("keydown", (event) => { if (event.key === "Enter") { event.preventDefault(); confirmEdit(); } if (event.key === "Escape") window.jQuery($("#v3-edit-modal")).modal("hide"); });
        $("#v3-cart-items").addEventListener("click", (event) => { const row = event.target.closest(".v3-cart-row"); if (!row) return; const index = Number(row.dataset.index); const action = event.target.dataset.action; const item = state.cart[index]; if (action === "plus") add(item.product, item.productUnitId); else if (action === "minus") { item.qty--; if (item.qty <= 0) state.cart.splice(index, 1); render(); } else if (action === "remove") { state.cart.splice(index, 1); render(); } else if (action === "edit") openEdit(index); else openQuantity(item.product, item.productUnitId, index); });
        $("#v3-note-button").addEventListener("click", () => { $("#v3-note-input").value = state.note; window.jQuery($("#v3-note-modal")).modal("show"); }); $("#v3-note-confirm").addEventListener("click", () => { state.note = $("#v3-note-input").value.trim(); window.jQuery($("#v3-note-modal")).modal("hide"); }); $("#v3-new-bill").addEventListener("click", () => { if (!state.cart.length || confirm("ล้างตะกร้าและเริ่มบิลใหม่หรือไม่?")) { state.cart = []; state.discount = 0; state.note = ""; $("#v3-discount").value = "0.00"; render(); $("#v3-product-search").focus(); } });
        const payment = PosPayment.createController({ getTotal: () => money(total()), onConfirm: submit }); $("#v3-submit").addEventListener("click", () => { if (!state.cart.length) return alert("กรุณาเพิ่มสินค้าอย่างน้อย 1 รายการ"); payment.open(); });
        $("#v3-product-search").addEventListener("keydown", (event) => { if (event.key !== "Enter") return; const term = event.target.value.trim().toLowerCase(); const card = [...document.querySelectorAll(".v3-product-card")].find((candidate) => { const p = JSON.parse(candidate.dataset.product); return String(p.barcode || "").toLowerCase() === term || p.productUnits?.some((u) => u.barcodes?.some((b) => String(b.barcode).toLowerCase() === term)); }); if (card) { openQuantity(JSON.parse(card.dataset.product)); event.target.value = ""; } });
        document.addEventListener("keydown", (event) => { if (event.key === "F2" || event.key === "F8") { event.preventDefault(); $("#v3-product-search").focus(); $("#v3-product-search").select(); } if (event.key === "F9") { event.preventDefault(); $("#v3-submit").click(); } if (event.key === "Escape" && !document.querySelector(".modal.show")) $("#v3-product-search").focus(); }); setInterval(() => { $("#pos-v3-clock").textContent = new Date().toLocaleTimeString("th-TH", { hour: "2-digit", minute: "2-digit" }); }, 1000);
    }

    async function submit(payment) {
        const guard = window.SaleIntentStorage.createSubmissionGuard(); if (!guard.start()) return; const payload = buildPayload(window.PosPayment.payload(payment)); const pending = window.SaleIntentStorage.createManager({ storageKey: "atrilak.pos.v3.pending-sale.v1" }); let intent = null;
        try { intent = await pending.keyFor(payload); payload.idempotency_key = intent.key; const response = await fetch(root.dataset.storeUrl, { method: "POST", headers: { "Content-Type": "application/json", Accept: "application/json", "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify(payload) }); const data = await response.json(); if (!response.ok || !data.success) throw Object.assign(new Error(data.message || "บันทึกการขายไม่สำเร็จ"), { status: response.status }); pending.clear(intent.key); state.cart = []; render(); window.location.assign(data.invoice_url); } catch (error) { if (intent && window.SaleIntentStorage.isDefinitiveClientError(error.status)) pending.clear(intent.key); throw error; } finally { guard.release(); }
    }

    init();
})();
