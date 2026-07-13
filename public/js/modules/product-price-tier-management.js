document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('priceTierModal');
    const form = document.getElementById('priceTierForm');
    const formMethod = document.getElementById('priceTierFormMethod');
    const modalTitle = document.getElementById('priceTierModalLabel');
    const submitButton = document.getElementById('priceTierSubmitButton');

    const productUnitSelect = document.getElementById('priceTierProductUnit');
    const minQtyInput = document.getElementById('priceTierMinQty');
    const typeSelect = document.getElementById('priceTierType');
    const activeSelect = document.getElementById('priceTierActive');

    const discountGroup = document.getElementById('priceTierDiscountGroup');
    const discountInput = document.getElementById('priceTierDiscountPercent');

    const fixedPriceGroup = document.getElementById('priceTierFixedPriceGroup');
    const fixedPriceInput = document.getElementById('priceTierFixedPrice');

    const previewBox = document.getElementById('priceTierPreview');

    if (
        !modal ||
        !form ||
        !formMethod ||
        !modalTitle ||
        !submitButton ||
        !productUnitSelect ||
        !minQtyInput ||
        !typeSelect ||
        !activeSelect ||
        !discountGroup ||
        !discountInput ||
        !fixedPriceGroup ||
        !fixedPriceInput ||
        !previewBox
    ) {
        return;
    }

    function formatMoney(value) {
        return Number(value || 0).toLocaleString('th-TH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function getSelectedProductUnit() {
        return productUnitSelect.options[
            productUnitSelect.selectedIndex
        ] || null;
    }

    function updateTierTypeFields() {
        const type = typeSelect.value;

        if (type === 'fixed') {
            discountGroup.classList.add('d-none');
            fixedPriceGroup.classList.remove('d-none');

            discountInput.value = '';
            fixedPriceInput.required = true;
            discountInput.required = false;
        } else {
            fixedPriceGroup.classList.add('d-none');
            discountGroup.classList.remove('d-none');

            fixedPriceInput.value = '';
            discountInput.required = true;
            fixedPriceInput.required = false;
        }

        updatePreview();
    }

    function updatePreview() {
        const selectedOption = getSelectedProductUnit();
        const minQty = Number(minQtyInput.value || 0);
        const basePrice = Number(
            selectedOption?.dataset.sellingPrice || 0
        );

        if (!selectedOption || !selectedOption.value) {
            previewBox.textContent =
                'เลือกสินค้า หน่วยขาย และกรอกข้อมูล เพื่อดูตัวอย่างราคา';

            return;
        }

        if (minQty < 1) {
            previewBox.textContent =
                'กรุณากรอกจำนวนขั้นต่ำอย่างน้อย 1';

            return;
        }

        let resultPrice = basePrice;
        let description = '';

        if (typeSelect.value === 'fixed') {
            const fixedPrice = Number(fixedPriceInput.value || 0);

            resultPrice = fixedPrice;
            description =
                'กำหนดราคาคงที่ ' +
                formatMoney(fixedPrice) +
                ' บาท';
        } else {
            const discountPercent = Number(
                discountInput.value || 0
            );

            resultPrice =
                basePrice *
                (1 - (discountPercent / 100));

            description =
                'ลด ' +
                discountPercent.toLocaleString('th-TH') +
                '%';
        }

        const productName =
            selectedOption.dataset.productName || '-';

        const unitName =
            selectedOption.dataset.unitName || '-';

        previewBox.innerHTML =
            '<strong>' + productName + '</strong>' +
            ' — ' + unitName +
            '<br>' +
            'ตั้งแต่ ' +
            minQty.toLocaleString('th-TH') +
            ' หน่วยขึ้นไป' +
            '<br>' +
            description +
            '<br>' +
            'ราคาปกติ ' +
            formatMoney(basePrice) +
            ' บาท' +
            ' → ' +
            '<strong>' +
            formatMoney(resultPrice) +
            ' บาท</strong>';
    }

    function resetCreateForm(productUnitId) {
        form.reset();

        form.action = '/product-price-tiers/store';
        formMethod.value = 'POST';

        modalTitle.textContent = 'เพิ่ม Price Tier';
        submitButton.textContent = 'บันทึก Price Tier';

        productUnitSelect.disabled = false;
        productUnitSelect.value = String(productUnitId || '');

        minQtyInput.value = '';
        typeSelect.value = 'discount';
        activeSelect.value = '1';
        discountInput.value = '0';
        fixedPriceInput.value = '';

        updateTierTypeFields();
        updatePreview();
    }

    function initAddTierButtons() {
    const addButtons = document.querySelectorAll(
        '.btn-add-tier'
    );

    addButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const productUnitId =
                button.dataset.productUnitId || '';

            resetCreateForm(productUnitId);

            $('#priceTierModal').modal('show');
        });
    });
}

function openEditForm(button) {
    const tierId = button.dataset.tierId || '';
    const productUnitId =
        button.dataset.productUnitId || '';

    const minQty =
        button.dataset.minQty || '';

    const discountPercent =
        button.dataset.discountPercent || '';

    const fixedPrice =
        button.dataset.fixedPrice || '';

    const active =
        button.dataset.active || '0';

    form.reset();

    form.action =
        '/product-price-tiers/' + tierId;

    formMethod.value = 'PUT';

    modalTitle.textContent = 'แก้ไข Price Tier';
    submitButton.textContent = 'บันทึกการแก้ไข';

    productUnitSelect.value =
        String(productUnitId);

    productUnitSelect.disabled = true;

    minQtyInput.value = minQty;
    activeSelect.value = active;

    if (
        fixedPrice !== '' &&
        fixedPrice !== null
    ) {
        typeSelect.value = 'fixed';

        fixedPriceInput.value =
            fixedPrice;

        discountInput.value = '';
    } else {
        typeSelect.value = 'discount';

        discountInput.value =
            discountPercent || '0';

        fixedPriceInput.value = '';
    }

    updateTierTypeFields();
    updatePreview();

    $('#priceTierModal').modal('show');
}

function initEditTierButtons() {
    const editButtons = document.querySelectorAll(
        '.btn-edit-tier'
    );

    editButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            openEditForm(button);
        });
    });
}

    function initTierType() {
        typeSelect.addEventListener(
            'change',
            updateTierTypeFields
        );
    }

    function initPreview() {
        productUnitSelect.addEventListener(
            'change',
            updatePreview
        );

        minQtyInput.addEventListener(
            'input',
            updatePreview
        );

        discountInput.addEventListener(
            'input',
            updatePreview
        );

        fixedPriceInput.addEventListener(
            'input',
            updatePreview
        );
    }

    initAddTierButtons();
initEditTierButtons();
initTierType();
initPreview();

updateTierTypeFields();
});
