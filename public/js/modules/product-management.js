(function () {
    'use strict';

    const modal = document.getElementById('productModal');
    if (!modal) return;

    const form = document.getElementById('productModalForm');
    const method = document.getElementById('productModalMethod');
    const imageInput = document.getElementById('productModalImage');
    const imagePreview = document.getElementById('productModalImagePreview');
    const placeholder = imagePreview ? imagePreview.src : '';
    const fields = {
        name: document.getElementById('productModalName'),
        productCode: document.getElementById('productModalCode'),
        category: document.getElementById('productModalCategory'),
        unit: document.getElementById('productModalUnit'),
        barcode: document.getElementById('productModalBarcode'),
        active: document.getElementById('productModalActive'),
        remark: document.getElementById('productModalRemark'),
        cost: document.getElementById('productModalCost'),
        selling: document.getElementById('productModalSelling')
    };

    const setValue = (field, value) => { if (field) field.value = value ?? ''; };
    const updateAutoNumberHints = () => {
        if (!fields.category || method.value !== 'POST') return;

        const option = fields.category.options[fields.category.selectedIndex];
        const codePrefix = option ? option.dataset.codePrefix : '';
        const barcodePrefix = option ? option.dataset.barcodePrefix : '';
        document.getElementById('productModalCodeHint').textContent = codePrefix
            ? `รูปแบบรหัสสินค้า: ${codePrefix}-XXXX`
            : 'ระบบจะสร้างอัตโนมัติ';
        document.getElementById('productModalBarcodeHint').textContent = barcodePrefix
            ? `ใช้ Prefix ${barcodePrefix} สร้าง EAN-13 อัตโนมัติ`
            : 'ระบบจะสร้าง EAN-13 อัตโนมัติ';
    };
    const setReadOnlySummary = (product) => {
        document.getElementById('productReadOnlyCost').textContent = Number(product.cost_price || 0).toFixed(2);
        document.getElementById('productReadOnlySelling').textContent = Number(product.selling_price || 0).toFixed(2);
        document.getElementById('productReadOnlyProfit').textContent = `${Number(product.profit_percent || 0).toFixed(1)}%`;
        document.getElementById('productReadOnlyStock').textContent = Number(product.stock_qty || 0).toFixed(2);
        document.getElementById('productReadOnlyStockValue').textContent = Number(product.stock_value || 0).toFixed(2);
        document.getElementById('productUsageSummary').textContent = `ซื้อเข้า ${product.purchase_count || 0} รายการ · ขายออก ${product.sale_count || 0} รายการ · สร้างเมื่อ ${product.created_at || '—'} · แก้ไขล่าสุด ${product.updated_at || '—'}`;
    };

    $(modal).on('show.bs.modal', function (event) {
        const trigger = event.relatedTarget;
        const mode = trigger ? trigger.dataset.productMode : 'create';
        const product = trigger && trigger.dataset.product ? JSON.parse(trigger.dataset.product) : null;
        const isDetails = mode === 'details' && product;

        document.getElementById('productModalTitle').textContent = isDetails ? 'รายละเอียดสินค้า' : 'เพิ่มสินค้า';
        document.getElementById('productModalSubtitle').textContent = isDetails ? 'แก้ไขข้อมูลพื้นฐานและสถานะการใช้งาน' : 'ข้อมูลพื้นฐานและราคาเริ่มต้น';
        document.getElementById('productInitialPriceSection').classList.toggle('d-none', Boolean(isDetails));
        document.getElementById('productReadOnlySection').classList.toggle('d-none', !isDetails);
        document.getElementById('productModalSubmit').textContent = isDetails ? 'บันทึกการแก้ไข' : 'บันทึก';
        form.action = isDetails ? `/products/${product.id}` : '/products';
        method.value = isDetails ? 'PUT' : 'POST';
        document.getElementById('productModalId').value = isDetails ? product.id : '';
        fields.cost.disabled = Boolean(isDetails);
        fields.selling.disabled = Boolean(isDetails);
        fields.productCode.readOnly = true;
        fields.barcode.readOnly = true;

        if (isDetails) {
            setValue(fields.name, product.name);
            setValue(fields.productCode, product.product_code || product.sku);
            setValue(fields.category, product.category_id);
            setValue(fields.unit, product.unit_id);
            setValue(fields.barcode, product.barcode);
            document.getElementById('productModalCodeHint').textContent = 'สร้างไว้แล้วและแก้ไขไม่ได้';
            document.getElementById('productModalBarcodeHint').textContent = 'สร้างไว้แล้วและแก้ไขไม่ได้';
            setValue(fields.active, product.active ? '1' : '0');
            setValue(fields.remark, product.remark);
            imagePreview.src = product.image_path ? `/storage/${product.image_path}` : placeholder;
            setReadOnlySummary(product);
        } else {
            form.reset();
            method.value = 'POST';
            imagePreview.src = placeholder;
            setValue(fields.productCode, '');
            setValue(fields.barcode, '');
            fields.productCode.placeholder = 'ระบบจะสร้างอัตโนมัติ';
            fields.barcode.placeholder = 'ระบบจะสร้าง EAN-13 อัตโนมัติ';
            document.getElementById('productModalCodeHint').textContent = 'ระบบจะสร้างอัตโนมัติ';
            document.getElementById('productModalBarcodeHint').textContent = 'ระบบจะสร้าง EAN-13 อัตโนมัติ';
            updateAutoNumberHints();
        }
    });

    fields.category.addEventListener('change', updateAutoNumberHints);

    if (modal.dataset.validationErrors === '1') {
        const oldProductId = modal.dataset.oldProductId;
        if (oldProductId) {
            form.action = `/products/${oldProductId}`;
            method.value = 'PUT';
        }
        $('#productModal').modal('show');
    }

    if (imageInput) {
        imageInput.addEventListener('change', function () {
            const file = this.files && this.files[0];
            if (file) imagePreview.src = URL.createObjectURL(file);
        });
    }
})();
