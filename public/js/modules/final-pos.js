(function () {
    let context = null;
    let holding = false;
    const $ = (selector) => document.querySelector(selector);
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[character]));
    const money = (value) => Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const jsonHeaders = () => ({ 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content });

    function configure(next) {
        context = next;
        context.setCustomer = context.setCustomer || (async (customerId, preferredAddressId = null) => { context.customerSelect.value = customerId ? String(customerId) : ''; await context.loadAddresses(customerId, preferredAddressId); });
        context.setAddress = context.setAddress || ((addressId) => { context.addressSelect.value = addressId ? String(addressId) : ''; context.addressSelect.dispatchEvent(new Event('change')); });
        context.setDeliveryType = context.setDeliveryType || ((deliveryType) => { context.state.deliveryType = deliveryType === 'pickup' ? 'pickup' : 'delivery'; $('#v3-pickup').checked = context.state.deliveryType === 'pickup'; $('#v3-pickup').dispatchEvent(new Event('change')); });
        bind(); syncCustomerDisplay();
    }
    function bind() {
        $('#v3-open-customer-search')?.addEventListener('click', () => window.jQuery('#customer-search-modal').modal('show'));
        $('#v3-clear-customer')?.addEventListener('click', clearCustomer);
        $('#v3-customer-search')?.addEventListener('input', filterCustomers);
        document.querySelectorAll('[data-customer-select]').forEach((button) => button.addEventListener('click', () => selectCustomer(button.closest('[data-customer-row]'))));
        document.querySelectorAll('[data-customer-expand]').forEach((button) => button.addEventListener('click', () => expandCustomer(button.closest('[data-customer-row]'))));
        document.querySelectorAll('[data-final-action="holds"]').forEach((button) => button.addEventListener('click', () => {
            if (context.state.cart.length) {
                holdBill();
                return;
            }
            window.jQuery('#hold-bill-modal').modal('show');
            loadHolds();
        }));
        document.querySelectorAll('[data-final-action="history"]').forEach((button) => button.addEventListener('click', () => window.jQuery('#sale-history-modal').modal('show')));
        $('#v3-hold-refresh')?.addEventListener('click', loadHolds);
        $('#v3-hold-search')?.addEventListener('input', loadHolds);
        $('#final-edit-items')?.addEventListener('click', () => window.jQuery('#payment-confirmation-modal').modal('hide'));
        $('#final-change-payment')?.addEventListener('click', () => { window.jQuery('#payment-confirmation-modal').modal('hide'); context.payment.open(); });
        $('#final-confirm-payment')?.addEventListener('click', async () => { try { await context.payment.confirmDefaultCash(); } catch (error) { alert(error.message || 'ไม่สามารถบันทึกการขายได้'); } });
        $('#final-finish-payment')?.addEventListener('click', () => { resetSale(); window.jQuery('#payment-confirmation-modal').modal('hide'); });
        $('#final-print-documents')?.addEventListener('click', printDocuments); if (document.createElement) { ensurePaymentMethodSummary(); ensureDocumentButtons(); }
    }
    function syncCustomerDisplay() {
        if (!context) return;
        const option = context.customerSelect.selectedOptions[0];
        $('#v3-customer-name').textContent = option?.dataset.name || 'ลูกค้ายังไม่ได้เลือก';
        $('#v3-customer-phone').innerHTML = `<i class="fas fa-phone"></i> ${escapeHtml(option?.dataset.phone || 'กรุณาเลือกลูกค้า')}`;
    }
    function clearCustomer() { context.customerSelect.value = ''; context.customerSelect.dispatchEvent(new Event('change')); syncCustomerDisplay(); }
    function resetSale() {
        context.state.cart = [];
        context.state.customerId = '';
        context.state.addressId = '';
        context.state.deliveryType = 'pickup';
        context.state.address = null;
        context.state.zone = null;
        context.state.deliveryFee = 0;
        context.state.deliveryFeeEdited = false;
        context.state.discount = 0;
        context.state.note = '';
        context.state.holdBillId = null;
        context.customerSelect.value = '';
        context.addressSelect.value = '';
        context.addressSelect.disabled = true;
        $('#v3-sale-date').value = new Date().toISOString().slice(0, 10);
        $('#v3-pickup').checked = true;
        $('#v3-discount').value = '0.00';
        context.render();
        syncCustomerDisplay();
    }
    function filterCustomers() { const keyword = ($('#v3-customer-search').value || '').toLowerCase(); let visible = 0; document.querySelectorAll('[data-customer-row]').forEach((row) => { const show = row.dataset.search.includes(keyword); row.hidden = !show; if (show) visible += 1; }); $('#v3-customer-empty')?.classList.toggle('d-none', visible !== 0); }
    async function selectCustomer(row) { await context.setCustomer(row.dataset.customerId); window.jQuery('#customer-search-modal').modal('hide'); }
    async function expandCustomer(row) {
        const panel = row?.nextElementSibling; const list = panel?.querySelector('[data-customer-address-list]');
        if (!panel || !list) return;
        panel.classList.toggle('d-none');
        if (panel.classList.contains('d-none') || panel.dataset.loaded) return;
        const response = await fetch(context.root.dataset.addressUrlTemplate.replace('__CUSTOMER__', row.dataset.customerId), { headers: { Accept: 'application/json' } });
        const addresses = response.ok ? await response.json() : [];
        panel.dataset.loaded = '1';
        list.innerHTML = addresses.length ? addresses.map((address) => `<button type="button" class="btn btn-light btn-block text-left mb-1" data-address-choice="${address.id}">${escapeHtml(address.label || address.address || '-')} · ${escapeHtml(address.delivery_zone?.name || 'ไม่ระบุโซน')}</button>`).join('') : 'ยังไม่มีที่อยู่จัดส่ง';
        list.querySelectorAll('[data-address-choice]').forEach((button) => button.addEventListener('click', async () => { await context.setCustomer(row.dataset.customerId, button.dataset.addressChoice); context.setAddress(button.dataset.addressChoice); window.jQuery('#customer-search-modal').modal('hide'); }));
    }
    async function holdBill() {
        if (!context.state.cart.length) return alert('กรุณาเพิ่มสินค้าอย่างน้อย 1 รายการ');
        if (holding) return;
        holding = true;
        try {
           const payload = { customer_id: context.customerSelect.value || null, customer_delivery_address_id: context.addressSelect.value || null, delivery_zone_id: context.state.zone?.id || null, delivery_zone_name_snapshot: context.state.zone?.name || null, delivery_zone_markup_percent_snapshot: context.state.zone?.price_markup_percent || null, delivery_zone_rounding_increment_snapshot: context.state.zone?.rounding_increment || null, delivery_zone_minimum_profit_snapshot: context.state.zone?.minimum_profit || null, sale_date: $('#v3-sale-date').value, delivery_type: $('#v3-pickup').checked ? 'pickup' : 'delivery', discount: Number(context.state.discount || 0).toFixed(2), delivery_fee: Number(context.state.deliveryFee || 0).toFixed(2), total_amount: context.total(), notes: context.state.note || null, items: context.state.cart.map((item) => ({ product_id: item.productId, product_unit_id: item.productUnitId, qty: item.qty, selling_price: Number(item.price).toFixed(2), price_was_edited: Boolean(item.priceWasEdited), price_changed_since_hold: Boolean(item.priceChangedSinceHold) })) };
            const response = await fetch(context.root.dataset.holdStoreUrl, { method: 'POST', headers: jsonHeaders(), body: JSON.stringify(payload) }); const data = await response.json();
            if (!response.ok || !data.success) return alert(data.message || 'พักบิลไม่สำเร็จ');
            resetSale(); alert(`พักบิล ${data.hold_bill.hold_no} เรียบร้อยแล้ว`);
        } finally {
            holding = false;
        }
    }
    async function loadHolds() { const response = await fetch(context.root.dataset.holdListUrl, { headers: { Accept: 'application/json' } }); const data = await response.json(); const list = $('#v3-hold-list'); list.innerHTML = (data.data || []).map((hold) => `<div class="final-hold-row d-flex justify-content-between border-bottom p-2"><span><strong>${escapeHtml(hold.hold_no)}</strong><small class="d-block">${escapeHtml(hold.customer?.name || 'ผู้ซื้อทั่วไป')} · ${hold.items?.length || 0} รายการ · ${money(hold.total_amount)} บาท</small></span><span><button class="btn btn-sm btn-outline-success" data-resume-hold="${hold.id}">ชำระเงิน</button><button class="btn btn-sm btn-outline-danger" data-delete-hold="${hold.id}">ลบ</button></span></div>`).join(''); list.querySelectorAll('[data-resume-hold]').forEach((button) => button.addEventListener('click', () => resumeHold(button.dataset.resumeHold))); list.querySelectorAll('[data-delete-hold]').forEach((button) => button.addEventListener('click', () => deleteHold(button.dataset.deleteHold))); $('#v3-hold-empty')?.classList.toggle('d-none', Boolean(data.data?.length)); }
    async function resumeHold(id) {
        const response = await fetch(context.root.dataset.holdUrlTemplate.replace('__HOLD__', id), { headers: { Accept: 'application/json' } });
        const data = await response.json();
        if (!response.ok || !data.success) return alert('โหลดพักบิลไม่สำเร็จ');
        const hold = data.data;
        if ((hold.items || []).some((item) => !item.product || (item.product_unit_id_snapshot && (!item.product_unit || item.product_unit.active === false)))) return alert('ไม่สามารถโหลดพักบิลได้ เนื่องจากมีสินค้าหรือหน่วยสินค้าที่ถูกลบหรือปิดใช้งาน');

        context.setDeliveryType(hold.delivery_type);
        await context.setCustomer(hold.customer_id ? String(hold.customer_id) : '', hold.customer_delivery_address_id ? String(hold.customer_delivery_address_id) : null);
        if (hold.customer_delivery_address_id) context.setAddress(hold.customer_delivery_address_id);
        $('#v3-sale-date').value = hold.sale_date || $('#v3-sale-date').value;
       context.state.cart = (hold.items || []).map((item) => ({ key: `${item.product.id}:${item.product_unit_id || 'base'}`, productId: item.product.id, productUnitId: item.product_unit_id, product: item.product, unit: item.product_unit, unitName: item.unit_name_snapshot || item.product.unit, name: item.product_name_snapshot, qty: Number(item.qty), price: Number(item.selling_price), originalPrice: item.original_price === null || item.original_price === undefined ? null : Number(item.original_price), priceWasEdited: item.price_override_flag === true || item.price_override_flag === 1 || item.price_override_flag === '1', priceChangedSinceHold: false }));
        context.state.zone = hold.delivery_zone_id ? { id: hold.delivery_zone_id, name: hold.delivery_zone_name_snapshot, price_markup_percent: hold.delivery_zone_markup_percent_snapshot, rounding_increment: hold.delivery_zone_rounding_increment_snapshot, minimum_profit: hold.delivery_zone_minimum_profit_snapshot, active: true } : null;
        context.state.discount = Number(hold.discount || 0);
        context.state.deliveryFee = hold.delivery_type === 'pickup' ? 0 : Number(hold.delivery_fee || 0);
        context.state.deliveryFeeEdited = hold.delivery_type !== 'pickup';
        context.state.note = hold.notes || '';
        context.state.holdBillId = Number(hold.id);
        $('#v3-discount').value = money(context.state.discount);
        context.render();

        window.jQuery('#hold-bill-modal').modal('hide');
    }
    async function deleteHold(id) { if (!confirm('ยืนยันลบรายการพักบิล?')) return; const response = await fetch(context.root.dataset.holdUrlTemplate.replace('__HOLD__', id), { method: 'DELETE', headers: jsonHeaders() }); if (!response.ok) return alert('ลบรายการพักบิลไม่สำเร็จ'); loadHolds(); }
    function openConfirmation() { const modal = $('#payment-confirmation-modal'); modal?.classList.remove('has-documents'); $('#final-payment-close')?.classList.remove('d-none'); $('#final-document-panel')?.classList.add('d-none'); $('#final-payment-status')?.classList.add('d-none'); $('#final-confirm-payment')?.classList.remove('d-none'); $('#final-edit-items')?.classList.remove('d-none'); $('#final-print-documents').disabled = true; $('#final-finish-payment').disabled = true; $('#final-preview-sale-no').textContent = 'รอออกเลขที่บิล'; const saleDate = $('#v3-sale-date')?.value || '-'; $('#final-preview-bill-date').textContent = saleDate; const pickup = $('#v3-pickup')?.checked; const address = context.addressSelect.selectedOptions[0]; $('#final-preview-address').textContent = pickup ? 'รับสินค้าเองที่ร้าน' : (address?.textContent || 'ยังไม่ได้เลือกที่อยู่จัดส่ง'); $('#final-preview-fulfillment').textContent = pickup ? 'รับเอง' : 'จัดส่ง'; $('#final-preview-zone').textContent = pickup ? 'ไม่มีค่าส่ง' : (context.state.zone?.name ? `โซนจัดส่ง: ${context.state.zone.name}` : 'ยังไม่ได้เลือกโซน'); $('#final-preview-date-label').textContent = pickup ? 'วันที่รับสินค้า' : 'วันที่จัดส่ง'; $('#final-preview-date').textContent = saleDate; $('#final-preview-items').innerHTML = context.state.cart.map((item, index) => `<tr><td>${index + 1}</td><td>${escapeHtml(item.name)}</td><td>${escapeHtml(item.unitName)}</td><td>${item.qty}</td><td>${money(item.price)}</td><td>${money(item.qty * item.price)}</td></tr>`).join(''); $('#final-preview-item-count').textContent = `${context.state.cart.length} รายการ`; $('#final-preview-subtotal').textContent = money(context.state.cart.reduce((sum, item) => sum + item.qty * item.price, 0)); $('#final-preview-discount').textContent = money(context.state.discount); $('#final-preview-delivery').textContent = money(context.state.deliveryFee); $('#final-preview-total').textContent = money(context.total()); $('#final-payable-total').textContent = money(context.total()); const option = context.customerSelect.selectedOptions[0]; $('#final-preview-customer').textContent = option?.dataset.name || 'ผู้ซื้อทั่วไป'; $('#final-preview-phone').textContent = option?.dataset.phone || '-'; $('#final-print-tax').disabled = !option?.dataset.taxNumber; window.jQuery('#payment-confirmation-modal').modal('show'); }
    function normalizePaymentSnapshot(data) { const payment = data?.payment || data?.payment_snapshot || data || {}; return { method: ['cash', 'promptpay', 'mixed'].includes(String(payment.payment_method)) ? String(payment.payment_method) : null, cash: payment.cash_amount, promptpay: payment.promptpay_amount, received: payment.received_amount, change: payment.change_amount }; }
    function updatePaymentMethodSummary(data) { const payment = normalizePaymentSnapshot(data); const labels = { cash: 'เงินสด', promptpay: 'พร้อมเพย์', mixed: 'เงินสด + พร้อมเพย์' }; const label = $('#final-payment-method-label'); const amounts = $('#final-payment-amounts'); if (label) label.textContent = `วิธีชำระเงิน: ${labels[payment.method] || 'ไม่ระบุ'}`; if (amounts) amounts.textContent = payment.method ? `เงินสด ${money(payment.cash)} · พร้อมเพย์ ${money(payment.promptpay)} · รับเงิน ${money(payment.received)} · เงินทอน ${money(payment.change)}` : ''; }
    function showSuccess(data) { updatePaymentMethodSummary(data); context.lastSaleId = data.sale_id; $('#final-payment-close')?.classList.add('d-none'); $('#final-payment-status')?.classList.remove('d-none'); $('#final-preview-sale-no').textContent = `เลขที่บิล ${data.sale_no || '-'}`; $('#final-confirm-payment')?.classList.add('d-none'); $('#final-edit-items')?.classList.add('d-none'); $('#final-document-panel')?.classList.remove('d-none'); $('#payment-confirmation-modal')?.classList.add('has-documents'); $('#final-print-documents').disabled = false; $('#final-finish-payment').disabled = false; window.jQuery('#payment-confirmation-modal').modal('show'); }
    function ensurePaymentMethodSummary() { const confirm = $('#final-confirm-payment'); if (!confirm || $('#final-payment-method-summary') || !document.createElement) return; const summary = document.createElement('div'); summary.id = 'final-payment-method-summary'; summary.className = 'final-payment-method-summary'; const label = document.createElement('strong'); label.id = 'final-payment-method-label'; label.textContent = 'วิธีชำระเงิน: ยังไม่ได้ยืนยัน'; const amounts = document.createElement('small'); amounts.id = 'final-payment-amounts'; const change = document.createElement('button'); change.id = 'final-change-payment'; change.type = 'button'; change.className = 'btn btn-link btn-sm'; change.textContent = 'เปลี่ยนวิธีชำระเงิน'; change.addEventListener('click', () => { window.jQuery('#payment-confirmation-modal').modal('hide'); context.payment.open(); }); summary.append(label, amounts, change); confirm.parentElement?.insertBefore(summary, confirm); }
    function printDocuments() { const saleId = context.lastSaleId; ['delivery-note', 'tax-invoice'].filter((type) => type === 'delivery-note' ? $('#final-print-delivery').checked : $('#final-print-tax').checked && !$('#final-print-tax').disabled).forEach((type) => window.open(`${context.root.dataset.documentUrlTemplate.replace('__SALE__', saleId)}?document_type=${type}`, '_blank', 'noopener')); }
    function printDocument(type, preview = false) { const saleId = context.lastSaleId; if (!saleId) return; const base = context.root.dataset.documentUrlTemplate.replace('__SALE__', saleId); const query = `?document_type=${type}${preview ? '&preview=1' : ''}`; window.open(`${base}${query}`, '_blank', 'noopener'); }
    function ensureDocumentButtons() { const panel = $('#final-document-panel'); const print = $('#final-print-documents'); if (!panel || !print || !document.createElement || panel.querySelector?.('[data-final-document-actions]')) return; const actions = document.createElement('div'); actions.dataset.finalDocumentActions = '1'; actions.className = 'final-document-actions'; [['delivery-note', 'พิมพ์ใบส่งของ'], ['tax-invoice', 'พิมพ์ใบกำกับภาษี']].forEach(([type, label]) => { const button = document.createElement('button'); button.type = 'button'; button.className = 'btn btn-outline-primary'; button.textContent = label; button.addEventListener('click', () => printDocument(type)); actions.append(button); }); const preview = document.createElement('button'); preview.type = 'button'; preview.className = 'btn btn-outline-secondary'; preview.textContent = 'ดูตัวอย่าง'; preview.addEventListener('click', () => printDocument('delivery-note', true)); actions.append(preview); panel.insertBefore(actions, print); }
    window.FinalPos = { configure, openConfirmation, showSuccess, syncCustomerDisplay };
})();
