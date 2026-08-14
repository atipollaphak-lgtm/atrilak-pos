(function () {
    let context = null;
    let holding = false;
    let deliveryEditorReturnFocus = null;
    const printedDocuments = new Set();
    let printing = false;

    const $ = (selector) => document.querySelector(selector);
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[character]));
    const money = (value) => Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const jsonHeaders = () => ({ 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content });
    const deliveryDateField = () => $('#v3-delivery-date') || $('#v3-sale-date');

    function saleDate() {
        return context?.root?.dataset?.saleDate || new Date().toISOString().slice(0, 10);
    }

    function showFeedback(message, tone = 'success') {
        const feedback = $('#v3-action-feedback');
        if (!feedback) return;
        feedback.textContent = message;
        feedback.classList.remove('d-none', 'is-success', 'is-error');
        feedback.classList.add(tone === 'error' ? 'is-error' : 'is-success');
    }

    function configure(next) {
        context = next;
        context.setCustomer = context.setCustomer || (async (customerId, preferredAddressId = null) => {
            context.customerSelect.value = customerId ? String(customerId) : '';
            await context.loadAddresses(customerId, preferredAddressId);
        });
        context.setAddress = context.setAddress || ((addressId) => {
            context.addressSelect.value = addressId ? String(addressId) : '';
            context.addressSelect.dispatchEvent(new Event('change'));
        });
        context.setDeliveryType = context.setDeliveryType || ((deliveryType) => {
            context.state.deliveryType = deliveryType === 'pickup' ? 'pickup' : 'delivery';
            $('#v3-pickup').checked = context.state.deliveryType === 'pickup';
            $('#v3-pickup').dispatchEvent(new Event('change'));
        });
        bind();
        syncCustomerDisplay();
        syncDeliveryEditor();
    }

    function bind() {
        $('#v3-open-customer-search')?.addEventListener('click', () => openDeliveryEditor('customer'));
        $('#v3-clear-customer')?.addEventListener('click', clearCustomer);
        $('#v3-customer-search')?.addEventListener('input', filterCustomers);
        filterCustomers();
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
        $('#final-change-payment')?.addEventListener('click', () => {
            window.jQuery('#payment-confirmation-modal').modal('hide');
            context.payment.open();
        });
        $('#final-confirm-payment')?.addEventListener('click', async () => {
            try {
                await context.payment.confirmDefaultCash();
            } catch (error) {
                alert(error.message || 'ไม่สามารถบันทึกการขายได้');
            }
        });
        $('#final-finish-payment')?.addEventListener('click', () => {
            resetSale();
            window.jQuery('#payment-confirmation-modal').modal('hide');
        });
        $('#final-print-delivery')?.addEventListener('click', () => printDocument('delivery-note'));
        $('#final-print-tax')?.addEventListener('click', () => printDocument('tax-invoice'));
        document.querySelectorAll('#v3-open-customer-create, #v3-open-customer-create-from-search, #v3-open-customer-create-from-bar, #v3-customer-empty-create').forEach((button) => button.addEventListener('click', openCustomerCreate));
        $('#v3-customer-create-form')?.addEventListener('submit', createCustomer);
        $('#v3-address-retry')?.addEventListener('click', async () => {
            if (!context?.state?.customerId || context.state.addressLoading) return;
            await context.setCustomer(context.state.customerId);
            syncDeliveryEditor();
            focusDeliveryEditor('address');
        });
        $('#v3-delivery-editor-confirm')?.addEventListener('click', () => {
            if (context.state.deliveryType === 'delivery' && (!context.state.customerId || !context.state.addressId)) {
                focusDeliveryEditor(context.state.customerId ? 'address' : 'customer');
                return;
            }
            window.jQuery('#customer-search-modal').modal('hide');
        });
        if (document.createElement) {
            ensurePaymentMethodSummary();
        }
    }

    function syncCustomerDisplay() {
        if (!context) return;
        const option = context.customerSelect.selectedOptions[0];
        const customerName = option?.dataset.name || 'ลูกค้าทั่วไป';
        const customerPhone = String(option?.dataset.phone || '').trim();
        const customerId = String(context.customerSelect.value || option?.value || '').trim();
        const summary = customerId && customerPhone ? `${customerName} (${customerPhone})` : customerName;
        const summaryElement = $('#v3-customer-summary');
        if (summaryElement) summaryElement.textContent = summary;
        const legacyName = $('#v3-customer-name');
        if (legacyName) legacyName.textContent = customerName;
        const legacyPhone = $('#v3-customer-phone');
        if (legacyPhone) legacyPhone.innerHTML = `<i class="fas fa-phone"></i> ${escapeHtml(customerPhone || 'ไม่ระบุข้อมูลลูกค้า')}`;

        const details = $('#v3-customer-details');
        if (details) {
            details.href = customerId ? context.root.dataset.customerShowUrlTemplate.replace('__CUSTOMER__', customerId) : '#';
            details.ariaDisabled = customerId ? 'false' : 'true';
            details.tabIndex = customerId ? 0 : -1;
            details.classList.toggle('disabled', !customerId);
            details.classList.toggle('is-disabled', !customerId);
        }
    }

    function focusDeliveryEditor(target = 'customer') {
        if (target === 'address') {
            const selected = document.querySelector('#v3-delivery-address-list [aria-pressed="true"]');
            const firstAddress = document.querySelector('#v3-delivery-address-list [data-address-choice]');
            (selected || firstAddress || $('#v3-delivery-editor-detail'))?.focus?.();
            return;
        }
        $('#v3-customer-search')?.focus?.();
        $('#v3-customer-search')?.select?.();
    }

    function openDeliveryEditor(focusTarget = 'customer') {
        const modal = $('#customer-search-modal');
        if (!modal) return;
        deliveryEditorReturnFocus = document.activeElement;
        modal.dataset.focusTarget = focusTarget;
        syncDeliveryEditor();
        const instance = window.jQuery(modal);
        instance.one('shown.bs.modal', () => focusDeliveryEditor(modal.dataset.focusTarget || 'customer'));
        instance.one('hidden.bs.modal', () => {
            const target = deliveryEditorReturnFocus;
            deliveryEditorReturnFocus = null;
            target?.focus?.();
        });
        instance.modal('show');
    }

    function syncDeliveryEditor() {
        if (!context) return;
        const detail = $('#v3-delivery-editor-detail');
        const status = $('#v3-delivery-editor-status');
        const list = $('#v3-delivery-address-list');
        const loadError = $('#v3-address-load-error');
        const noAddress = $('#v3-no-address');
        const heading = $('#v3-address-list-title');
        const meta = $('#v3-delivery-customer-meta');
        const manage = $('#v3-manage-customer-addresses');
        const confirm = $('#v3-delivery-editor-confirm');
        const customerId = String(context.state.customerId || '');
        const option = context.customerSelect.selectedOptions[0];
        const customerName = customerId ? (option?.dataset.name || option?.textContent || 'ลูกค้าที่เลือก') : 'ยังไม่ได้เลือกลูกค้า';
        const customerPhone = String(option?.dataset.phone || '').trim();

        detail?.setAttribute('aria-busy', context.state.addressLoading ? 'true' : 'false');
        loadError?.classList.toggle('d-none', !context.state.addressLoadFailed);
        noAddress?.classList.add('d-none');
        if (heading) heading.textContent = customerName;
        if (meta) meta.textContent = customerId ? (customerPhone || 'ไม่ระบุเบอร์โทร') : 'เลือกลูกค้าเพื่อดูที่อยู่จัดส่ง';
        if (manage) manage.href = customerId ? context.root.dataset.customerShowUrlTemplate.replace('__CUSTOMER__', customerId) : '#';
        if (confirm) confirm.disabled = context.state.deliveryType === 'delivery' && (!customerId || !context.state.addressId);
        if (!list) return;

        if (!customerId) {
            list.innerHTML = '<div class="final-empty">เลือกลูกค้าและที่อยู่</div>';
            if (status) status.textContent = 'เลือกลูกค้าและที่อยู่';
            return;
        }
        if (context.state.addressLoading) {
            list.innerHTML = '<div class="pos-v3-address-loading"><i class="fas fa-spinner fa-spin" aria-hidden="true"></i> กำลังโหลดที่อยู่...</div>';
            if (status) status.textContent = 'กำลังโหลดที่อยู่';
            return;
        }
        if (context.state.addressLoadFailed) {
            list.innerHTML = '';
            if (status) status.textContent = 'โหลดที่อยู่ไม่สำเร็จ';
            return;
        }
        if (!context.state.addresses.length) {
            list.innerHTML = '';
            noAddress?.classList.remove('d-none');
            if (status) status.textContent = 'ลูกค้านี้ยังไม่มีที่อยู่จัดส่ง';
            return;
        }

        list.innerHTML = context.state.addresses.map((address) => {
            const selected = String(address.id) === String(context.state.addressId || '');
            const zone = address.delivery_zone?.name || 'ยังไม่ระบุโซนจัดส่ง';
            return `<button type="button" class="pos-v3-address-choice${selected ? ' is-selected' : ''}" data-address-choice="${address.id}" aria-pressed="${selected ? 'true' : 'false'}"><span class="pos-v3-address-check" aria-hidden="true">${selected ? '✓' : ''}</span><strong>${escapeHtml(address.label || address.name || `ที่อยู่ ${address.id}`)}</strong><small>${escapeHtml(address.address || '-')}</small><span>${escapeHtml(zone)}</span></button>`;
        }).join('');
        list.querySelectorAll('[data-address-choice]').forEach((button) => button.addEventListener('click', async () => {
            await context.setAddress(button.dataset.addressChoice);
            syncDeliveryEditor();
            if (context.state.addressId) window.jQuery('#customer-search-modal').modal('hide');
        }));
        if (status) status.textContent = context.state.addressId ? 'เลือกที่อยู่จัดส่งแล้ว' : `พบ ${context.state.addresses.length} ที่อยู่ กรุณาเลือก`;
    }

    function clearCustomer() {
        context.customerSelect.value = '';
        context.customerSelect.dispatchEvent(new Event('change'));
        syncCustomerDisplay();
        syncDeliveryEditor();
    }

    function openCustomerCreate() {
        $('#v3-customer-create-form')?.reset?.();
        $('#v3-customer-create-error')?.classList.add('d-none');
        window.jQuery('#customer-search-modal').modal('hide');
        window.jQuery('#v3-customer-create-modal').modal('show');
    }

    async function createCustomer(event) {
        event.preventDefault();
        const form = event.target;
        const submit = $('#v3-customer-create-submit');
        const errorBox = $('#v3-customer-create-error');
        if (submit) submit.disabled = true;
        errorBox?.classList.add('d-none');
        try {
            const response = await fetch(context.root.dataset.customerStoreUrl, {
                method: 'POST',
                headers: jsonHeaders(),
                body: JSON.stringify(Object.fromEntries(new FormData(form).entries())),
            });
            const data = await response.json();
            if (!response.ok || !data.success || !data.customer) throw new Error(data.message || 'บันทึกลูกค้าไม่สำเร็จ');
            const customer = data.customer;
            const option = document.createElement('option');
            option.value = String(customer.id);
            option.textContent = customer.name;
            option.dataset.name = customer.name || '';
            option.dataset.phone = customer.phone || '';
            option.dataset.taxNumber = customer.tax_number || '';
            option.dataset.branchType = customer.branch_type || '';
            option.dataset.branchNumber = customer.branch_number || '';
            option.dataset.customerAddress = customer.address || '';
            option.dataset.addressCount = String(customer.delivery_addresses?.length || 0);
            context.customerSelect.append(option);
            await context.setCustomer(customer.id, customer.delivery_addresses?.length === 1 ? customer.delivery_addresses[0].id : null);
            window.jQuery('#v3-customer-create-modal').modal('hide');
            showFeedback(`เลือกลูกค้า ${customer.name} แล้ว`);
        } catch (error) {
            if (errorBox) {
                errorBox.textContent = error.message || 'บันทึกลูกค้าไม่สำเร็จ';
                errorBox.classList.remove('d-none');
            }
        } finally {
            if (submit) submit.disabled = false;
        }
    }

    function resetSale() {
        context.state.cart = [];
        context.state.customerId = '';
        context.state.addressId = '';
        context.state.deliveryType = 'delivery';
        context.state.address = null;
        context.state.addresses = [];
        context.state.addressLoading = false;
        context.state.addressLoadFailed = false;
        context.state.pricingZone = null;
        context.state.deliveryZone = null;
        context.state.deliveryFee = 0;
        context.state.deliveryFeeEdited = false;
        context.state.discount = 0;
        context.state.note = '';
        context.state.holdBillId = null;
        context.customerSelect.value = '';
        context.addressSelect.value = '';
        context.addressSelect.innerHTML = '<option value="">เลือกลูกค้าก่อน</option>';
        context.addressSelect.disabled = true;
        const date = deliveryDateField();
        if (date) date.value = saleDate();
        const formattedSaleDate = window.PosDate?.formatDisplay(saleDate()) || saleDate();
        const deliveryDateDisplay = $('#v3-delivery-date-display');
        if (deliveryDateDisplay) deliveryDateDisplay.value = formattedSaleDate;
        const legacySaleDateDisplay = $('#v3-sale-date-display');
        if (legacySaleDateDisplay) legacySaleDateDisplay.textContent = formattedSaleDate;
        $('#v3-pickup').checked = false;
        $('#v3-discount').value = '0.00';
        const technician = $('#v3-technician-id');
        if (technician) technician.value = '';
        context.payment?.reset?.();
        updatePaymentMethodSummary(null);
        printedDocuments.clear();
        ['delivery', 'tax'].forEach((suffix) => { const button = $(`#final-print-${suffix}`); if (button) button.disabled = true; });
        context.render();
        syncCustomerDisplay();
        syncDeliveryEditor();
    }

    function filterCustomers() {
        const keyword = ($('#v3-customer-search')?.value || '').toLowerCase();
        let visible = 0;
        document.querySelectorAll('[data-customer-row]').forEach((row) => {
            const show = row.dataset.search.includes(keyword);
            row.hidden = !show;
            if (show) visible += 1;
        });
        $('#v3-customer-empty')?.classList.toggle('d-none', visible !== 0);
    }

    async function selectCustomer(row) {
        if (!row) return;
        await context.setCustomer(row.dataset.customerId);
        syncDeliveryEditor();
        if (context.state.addresses.length === 1 && context.state.addressId) {
            window.jQuery('#customer-search-modal').modal('hide');
            return;
        }
        focusDeliveryEditor('address');
    }

    async function expandCustomer(row) {
        await selectCustomer(row);
    }

    async function holdBill() {
        if (!context.state.cart.length) return alert('กรุณาเพิ่มสินค้าอย่างน้อย 1 รายการ');
        if (holding) return;
        holding = true;
        try {
            const pickup = $('#v3-pickup').checked;
            const date = deliveryDateField();
            const pricingZone = context.state.pricingZone;
            const deliveryZone = pickup ? null : context.state.deliveryZone;
            const payload = {
                customer_id: context.customerSelect.value || null,
                customer_delivery_address_id: context.addressSelect.value || null,
                pricing_zone_id: pricingZone?.id || null,
                pricing_zone_name_snapshot: pricingZone?.name || null,
                pricing_zone_markup_percent_snapshot: pricingZone?.price_markup_percent || null,
                pricing_zone_rounding_increment_snapshot: pricingZone?.rounding_increment || null,
                delivery_zone_id: deliveryZone?.id || null,
                delivery_zone_name_snapshot: deliveryZone?.name || null,
                delivery_zone_markup_percent_snapshot: deliveryZone?.price_markup_percent || null,
                delivery_zone_rounding_increment_snapshot: deliveryZone?.rounding_increment || null,
                delivery_zone_minimum_profit_snapshot: deliveryZone?.minimum_profit || null,
                sale_date: saleDate(),
                delivery_date: pickup ? null : (date?.value || null),
                delivery_type: pickup ? 'pickup' : 'delivery',
                discount: Number(context.state.discount || 0).toFixed(2),
                delivery_fee: Number(context.state.deliveryFee || 0).toFixed(2),
                total_amount: context.total(),
                notes: context.state.note || null,
                items: context.state.cart.map((item) => ({
                    product_id: item.productId,
                    product_unit_id: item.productUnitId,
                    qty: item.qty,
                    selling_price: Number(item.price).toFixed(2),
                    price_was_edited: Boolean(item.priceWasEdited),
                })),
            };
            const response = await fetch(context.root.dataset.holdStoreUrl, { method: 'POST', headers: jsonHeaders(), body: JSON.stringify(payload) });
            const data = await response.json();
            if (!response.ok || !data.success) {
                showFeedback(data.message || 'พักบิลไม่สำเร็จ', 'error');
                return;
            }
            resetSale();
            showFeedback(`พักบิล ${data.hold_bill.hold_no} เรียบร้อยแล้ว`);
        } finally {
            holding = false;
        }
    }

    async function loadHolds() {
        const response = await fetch(context.root.dataset.holdListUrl, { headers: { Accept: 'application/json' } });
        const data = await response.json();
        const list = $('#v3-hold-list');
        list.innerHTML = (data.data || []).map((hold) => `<div class="final-hold-row d-flex justify-content-between border-bottom p-2"><span><strong>${escapeHtml(hold.hold_no)}</strong><small class="d-block">${escapeHtml(hold.customer?.name || 'ลูกค้าทั่วไป')} · ${hold.items?.length || 0} รายการ · ${money(hold.total_amount)} บาท</small></span><span><button class="btn btn-sm btn-outline-success" data-resume-hold="${hold.id}">ชำระเงิน</button><button class="btn btn-sm btn-outline-danger" data-delete-hold="${hold.id}">ลบ</button></span></div>`).join('');
        list.querySelectorAll('[data-resume-hold]').forEach((button) => button.addEventListener('click', () => resumeHold(button.dataset.resumeHold)));
        list.querySelectorAll('[data-delete-hold]').forEach((button) => button.addEventListener('click', () => deleteHold(button.dataset.deleteHold)));
        $('#v3-hold-empty')?.classList.toggle('d-none', Boolean(data.data?.length));
    }

    async function resumeHold(id) {
        const response = await fetch(context.root.dataset.holdUrlTemplate.replace('__HOLD__', id), { headers: { Accept: 'application/json' } });
        const data = await response.json();
        if (!response.ok || !data.success) return alert('โหลดพักบิลไม่สำเร็จ');
        const hold = data.data;
        if ((hold.items || []).some((item) => !item.product || (item.product_unit_id_snapshot && (!item.product_unit || item.product_unit.active === false)))) {
            return alert('ไม่สามารถโหลดพักบิลได้ เนื่องจากมีสินค้าหรือหน่วยสินค้าที่ถูกลบหรือปิดใช้งาน');
        }

        const heldPricingZone = hold.pricing_zone || (hold.pricing_zone_id ? {
            id: hold.pricing_zone_id,
            name: hold.pricing_zone_name_snapshot,
            price_markup_percent: hold.pricing_zone_markup_percent_snapshot,
            rounding_increment: hold.pricing_zone_rounding_increment_snapshot,
            active: true,
        } : null);
        const heldDeliveryZone = hold.delivery_type === 'delivery'
            ? (hold.delivery_zone || hold.customer_delivery_address?.delivery_zone || (hold.delivery_zone_id ? {
            id: hold.delivery_zone_id,
            name: hold.delivery_zone_name_snapshot,
            price_markup_percent: hold.delivery_zone_markup_percent_snapshot,
            rounding_increment: hold.delivery_zone_rounding_increment_snapshot,
            minimum_profit: hold.delivery_zone_minimum_profit_snapshot,
            active: true,
        } : null))
            : null;
        context.state.pricingZone = heldPricingZone;
        if (heldDeliveryZone) context.state.deliveryZone = heldDeliveryZone;
        context.setDeliveryType(hold.delivery_type);
        await context.setCustomer(hold.customer_id ? String(hold.customer_id) : '', hold.customer_delivery_address_id ? String(hold.customer_delivery_address_id) : null);
        if (hold.customer_delivery_address_id
            && context.addressSelect.value !== String(hold.customer_delivery_address_id)) {
            await context.setAddress(hold.customer_delivery_address_id);
        }
        context.state.pricingZone = heldPricingZone;
        if (heldDeliveryZone) context.state.deliveryZone = heldDeliveryZone;
        const date = deliveryDateField();
        if (date) date.value = hold.delivery_type === 'pickup' ? saleDate() : (hold.delivery_date || hold.sale_date || date.value);
        const formattedDeliveryDate = window.PosDate?.formatDisplay(date?.value) || date?.value || '';
        const deliveryDateDisplay = $('#v3-delivery-date-display');
        if (deliveryDateDisplay) deliveryDateDisplay.value = formattedDeliveryDate;
        const legacySaleDateDisplay = $('#v3-sale-date-display');
        if (legacySaleDateDisplay) legacySaleDateDisplay.textContent = window.PosDate?.formatDisplay(saleDate()) || saleDate();
        context.state.cart = (hold.items || []).map((item) => ({
            key: `${item.product.id}:${item.product_unit_id || 'base'}`,
            productId: item.product.id,
            productUnitId: item.product_unit_id,
            product: item.product,
            unit: item.product_unit,
            unitName: item.unit_name_snapshot || item.product.unit,
            name: item.product_name_snapshot,
            qty: Number(item.qty),
            price: Number(item.selling_price),
            originalPrice: item.original_price === null || item.original_price === undefined ? null : Number(item.original_price),
            priceWasEdited: item.price_override_flag === true || item.price_override_flag === 1 || item.price_override_flag === '1',
            priceChangedSinceHold: false,
        }));
        context.state.discount = Number(hold.discount || 0);
        context.state.deliveryFee = hold.delivery_type === 'pickup' ? 0 : Number(hold.delivery_fee || 0);
        context.state.deliveryFeeEdited = hold.delivery_type !== 'pickup';
        context.state.note = hold.notes || '';
        context.state.holdBillId = Number(hold.id);
        $('#v3-discount').value = money(context.state.discount);
        context.render();
        window.jQuery('#hold-bill-modal').modal('hide');
    }

    async function deleteHold(id) {
        if (!confirm('ยืนยันลบรายการพักบิล?')) return;
        const response = await fetch(context.root.dataset.holdUrlTemplate.replace('__HOLD__', id), { method: 'DELETE', headers: jsonHeaders() });
        if (!response.ok) return alert('ลบรายการพักบิลไม่สำเร็จ');
        loadHolds();
    }

    function updateTaxInvoiceAvailability(option) {
        const taxToggle = $('#final-print-tax');
        const help = $('#final-tax-help');
        if (!taxToggle) return;
        const missing = [];
        if (!String(option?.dataset.name || '').trim()) missing.push('ชื่อลูกค้า');
        if (!String(option?.dataset.taxNumber || '').trim()) missing.push('เลขประจำตัวผู้เสียภาษี');
        const invoiceAddress = option?.dataset.customerAddress || '';
        if (!String(invoiceAddress).trim()) missing.push('ที่อยู่ใบกำกับภาษี');
        if (option?.dataset.branchType === 'สาขา' && !String(option?.dataset.branchNumber || '').trim()) missing.push('เลขสาขา');
        const ready = missing.length === 0;
        taxToggle.disabled = !ready;
        if (!ready) taxToggle.checked = false;
        if (help) {
            help.textContent = ready ? '' : `ยังพิมพ์ใบกำกับภาษีไม่ได้: ข้อมูลไม่ครบ (${missing.join(', ')})`;
            help.classList.toggle('d-none', ready);
        }
    }

    function openConfirmation() {
        if (context.canConfirmDelivery && !context.canConfirmDelivery()) return;
        const modal = $('#payment-confirmation-modal');
        modal?.classList.remove('has-documents');
        $('#final-payment-close')?.classList.remove('d-none');
        $('#final-document-panel')?.classList.add('d-none');
        $('#final-payment-status')?.classList.add('d-none');
        $('#final-confirm-payment')?.classList.remove('d-none');
        $('#final-edit-items')?.classList.remove('d-none');
        const deliveryPrintButton = $('#final-print-delivery');
        const taxPrintButton = $('#final-print-tax');
        const finishButton = $('#final-finish-payment');
        if (deliveryPrintButton) deliveryPrintButton.disabled = true;
        if (taxPrintButton) taxPrintButton.disabled = true;
        if (finishButton) finishButton.disabled = true;
        $('#final-preview-sale-no').textContent = 'รอออกเลขที่บิล';
        context.payment?.reset?.();
        updatePaymentMethodSummary(null);

        const currentSaleDate = saleDate();
        const pickup = context.state.deliveryType === 'pickup';
        $('#final-payment-title').textContent = pickup ? 'ยืนยันการชำระเงิน' : 'ยืนยันการจัดส่ง';
        $('#final-payment-subtitle').textContent = pickup ? 'ตรวจสอบรายการสินค้าและยอดรวมก่อนยืนยันการชำระเงิน' : 'ตรวจสอบรายการสินค้า ที่อยู่ และยอดรวมก่อนยืนยันการจัดส่ง';
        const confirmButton = $('#final-confirm-payment');
        if (confirmButton) confirmButton.innerHTML = `<i class="fas fa-check mr-2"></i>${pickup ? 'ยืนยันการชำระเงิน' : 'ยืนยันการจัดส่ง'}`;
        const date = deliveryDateField();
        const selectedDeliveryDate = pickup ? currentSaleDate : (date?.value || '-');
        const displayDate = (value) => window.PosDate?.formatDisplay(value) || value || '-';
        const address = context.state.address;
        $('#final-preview-bill-date').textContent = displayDate(currentSaleDate);
        $('#final-preview-address').textContent = pickup ? 'รับสินค้าเองที่ร้าน' : (address?.address || address?.label || 'ยังไม่ได้เลือกที่อยู่จัดส่ง');
        $('#final-preview-fulfillment').textContent = pickup ? 'รับเอง' : 'จัดส่ง';
        $('#final-preview-zone').textContent = pickup ? 'ไม่มีค่าส่ง' : (context.state.deliveryZone?.name ? `โซนจัดส่ง: ${context.state.deliveryZone.name}` : 'ยังไม่ได้เลือกโซน');
        $('#final-preview-date-label').textContent = pickup ? 'วันที่รับสินค้า' : 'วันที่จัดส่ง';
        $('#final-preview-date').textContent = displayDate(selectedDeliveryDate);
        $('#final-preview-items').innerHTML = context.state.cart.map((item, index) => `<tr><td>${index + 1}</td><td>${escapeHtml(item.name)}</td><td>${item.qty} ${escapeHtml(item.unitName)}</td><td>${money(item.qty * item.price)}</td></tr>`).join('');
        $('#final-preview-item-count').textContent = `${context.state.cart.length} รายการ`;
        $('#final-preview-subtotal').textContent = money(context.state.cart.reduce((sum, item) => sum + item.qty * item.price, 0));
        $('#final-preview-discount').textContent = money(context.state.discount);
        $('#final-preview-delivery').textContent = money(context.state.deliveryFee);
        $('#final-preview-total').textContent = money(context.total());
        $('#final-payable-total').textContent = money(context.total());
        const option = context.customerSelect.selectedOptions[0];
        $('#final-preview-customer').textContent = option?.dataset.name || 'ลูกค้าทั่วไป';
        $('#final-preview-phone').textContent = option?.dataset.phone || '-';
        updateTaxInvoiceAvailability(option);
        window.jQuery('#payment-confirmation-modal').modal('show');
    }

    function normalizePaymentSnapshot(data) {
        const payment = data?.payment || data?.payment_snapshot || data || {};
        return {
            method: ['cash', 'promptpay', 'mixed'].includes(String(payment.payment_method)) ? String(payment.payment_method) : null,
            cash: payment.cash_amount,
            promptpay: payment.promptpay_amount,
            received: payment.received_amount,
            change: payment.change_amount,
        };
    }

    function updatePaymentMethodSummary(data) {
        const payment = normalizePaymentSnapshot(data);
        const labels = { cash: 'เงินสด', promptpay: 'พร้อมเพย์', mixed: 'เงินสด + พร้อมเพย์' };
        const label = $('#final-payment-method-label');
        const amounts = $('#final-payment-amounts');
        if (label) label.textContent = `วิธีชำระเงิน: ${labels[payment.method] || 'ยังไม่ได้ยืนยัน'}`;
        if (amounts) amounts.textContent = payment.method ? `เงินสด ${money(payment.cash)} · พร้อมเพย์ ${money(payment.promptpay)} · รับเงิน ${money(payment.received)} · เงินทอน ${money(payment.change)}` : '';
    }

    function showSuccess(data) {
        updatePaymentMethodSummary(data);
        context.lastSaleId = data.sale_id;
        const delivery = context.state.deliveryType === 'delivery';
        const status = $('#final-payment-status');
        if (status) status.textContent = delivery ? 'ยืนยันการจัดส่ง' : 'ชำระเงินเรียบร้อยแล้ว';
        status?.classList.toggle('is-delivery', delivery);
        $('#final-payment-title').textContent = delivery ? 'ยืนยันการจัดส่ง' : 'ชำระเงินเรียบร้อยแล้ว';
        $('#final-payment-subtitle').textContent = delivery ? 'ยืนยันการจัดส่ง' : 'บันทึกการขายและการชำระเงินเรียบร้อยแล้ว';
        $('#final-payment-close')?.classList.add('d-none');
        status?.classList.remove('d-none');
        $('#final-preview-sale-no').textContent = `เลขที่บิล ${data.sale_no || '-'}`;
        $('#final-confirm-payment')?.classList.add('d-none');
        $('#final-edit-items')?.classList.add('d-none');
        $('#final-document-panel')?.classList.remove('d-none');
        $('#payment-confirmation-modal')?.classList.add('has-documents');
        printedDocuments.clear();
        const deliveryButton = $('#final-print-delivery');
        if (deliveryButton) deliveryButton.disabled = false;
        updateTaxInvoiceAvailability(context.customerSelect.selectedOptions[0]);
        const finishButton = $('#final-finish-payment');
        if (finishButton) finishButton.disabled = false;
        window.jQuery('#payment-confirmation-modal').modal('show');
    }

    function ensurePaymentMethodSummary() {
        const confirm = $('#final-confirm-payment');
        if (!confirm || $('#final-payment-method-summary') || !document.createElement) return;
        const summary = document.createElement('div');
        summary.id = 'final-payment-method-summary';
        summary.className = 'final-payment-method-summary';
        const label = document.createElement('strong');
        label.id = 'final-payment-method-label';
        label.textContent = 'วิธีชำระเงิน: ยังไม่ได้ยืนยัน';
        const amounts = document.createElement('small');
        amounts.id = 'final-payment-amounts';
        const change = document.createElement('button');
        change.id = 'final-change-payment';
        change.type = 'button';
        change.className = 'btn btn-link btn-sm';
        change.textContent = 'เปลี่ยนวิธีชำระเงิน';
        change.addEventListener('click', () => {
            window.jQuery('#payment-confirmation-modal').modal('hide');
            context.payment.open();
        });
        summary.append(label, amounts, change);
        confirm.parentElement?.insertBefore(summary, confirm);
    }

    function printDocument(type, preview = false) {
        const saleId = context.lastSaleId;
        const button = type === 'delivery-note' ? $('#final-print-delivery') : $('#final-print-tax');
        if (!saleId || printing || printedDocuments.has(type) || (type === 'tax-invoice' && button?.disabled)) return;
        const base = context.root.dataset.documentUrlTemplate.replace('__SALE__', saleId);
        const query = `?document_type=${type}${preview ? '&preview=1' : ''}`;
        printing = true;
        if (button) button.disabled = true;
        const popup = window.open(`${base}${query}`, '_blank', 'noopener');
        if (!popup) {
            if (button) button.disabled = false;
            printing = false;
            showFeedback('เบราว์เซอร์บล็อกหน้าพิมพ์ กรุณาอนุญาต pop-up แล้วลองใหม่', 'error');
            return;
        }
        printedDocuments.add(type);
        window.setTimeout(() => { printing = false; }, 250);
    }

    window.FinalPos = { configure, openConfirmation, showSuccess, syncCustomerDisplay, syncDeliveryEditor, openDeliveryEditor, resetSale, showFeedback };
})();
