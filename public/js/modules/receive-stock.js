(function () {
    'use strict';

    const searchInput = document.getElementById('product-search');
    const searchButton = document.getElementById('search-product');
    const results = document.getElementById('product-results');
    const items = document.getElementById('receive-items');
    const total = document.getElementById('receive-total');
    const source = document.getElementById('source');
    const supplierField = document.getElementById('supplier-field');
    const form = document.getElementById('receive-stock-form');

    function updateSource() {
        const supplier = source.value === 'supplier';
        supplierField.style.display = supplier ? '' : 'none';
        document.getElementById('supplier_id').required = supplier;
        if (!supplier) document.getElementById('supplier_id').value = '';
    }

    function calculateTotal() {
        let value = 0;
        items.querySelectorAll('tr').forEach((row) => {
            const qty = Number(row.querySelector('.receive-qty').value || 0);
            const cost = Number(row.querySelector('.receive-cost').value || 0);
            const line = qty * cost;
            row.querySelector('.receive-line-total').textContent = line.toFixed(2);
            value += line;
        });
        total.textContent = value.toFixed(2);
    }

    function addProduct(product) {
        if ([...items.querySelectorAll('input[name$="[product_id]"]')].some((input) => input.value === String(product.id))) {
            window.alert('สินค้านี้ถูกเพิ่มแล้ว');
            return;
        }
        const index = items.querySelectorAll('tr').length;
        const units = product.units.length ? product.units : [{ id: '', name: 'หน่วยฐาน', code: '', conversion_rate: '1.0000' }];
        const row = document.createElement('tr');
        row.innerHTML = '<td><strong></strong><br><small class="text-muted"></small><input type="hidden" name="items[' + index + '][product_id]"></td>' +
            '<td><select class="form-control receive-unit" name="items[' + index + '][product_unit_id]"></select></td>' +
            '<td class="receive-stock"></td><td class="receive-average-cost"></td>' +
            '<td><input required min="0.0001" step="0.0001" type="number" class="form-control receive-qty" name="items[' + index + '][qty]" value="1"></td>' +
            '<td><input required min="0.01" step="0.01" type="number" class="form-control receive-cost" name="items[' + index + '][cost_price]" value=""></td>' +
            '<td class="receive-line-total">0.00</td><td><button type="button" class="btn btn-danger remove-receive-row">ลบ</button></td>';
        row.querySelector('strong').textContent = product.name;
        row.querySelector('small').textContent = [product.product_code, product.barcode].filter(Boolean).join(' / ');
        row.querySelector('input[type="hidden"]').value = product.id;
        row.querySelector('.receive-stock').textContent = product.stock_qty;
        row.querySelector('.receive-average-cost').textContent = product.cost_price;
        const unitSelect = row.querySelector('.receive-unit');
        units.forEach((unit) => {
            const option = document.createElement('option');
            option.value = unit.id;
            option.textContent = unit.name + (unit.code ? ' (' + unit.code + ')' : '') + ' x' + unit.conversion_rate;
            unitSelect.appendChild(option);
        });
        row.querySelector('.receive-cost').value = product.cost_price;
        items.appendChild(row);
        results.replaceChildren();
        calculateTotal();
    }

    async function search() {
        const query = searchInput.value.trim();
        if (!query) return;
        const response = await fetch('/receivings/products/search?q=' + encodeURIComponent(query), { headers: { Accept: 'application/json' } });
        const body = await response.json();
        results.replaceChildren();
        body.data.forEach((product) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'list-group-item list-group-item-action';
            button.textContent = product.name + ' | Stock ' + product.stock_qty + ' | Cost ' + product.cost_price;
            button.addEventListener('click', () => addProduct(product));
            results.appendChild(button);
        });
    }

    source.addEventListener('change', updateSource);
    searchButton.addEventListener('click', search);
    searchInput.addEventListener('keydown', (event) => { if (event.key === 'Enter') { event.preventDefault(); search(); } });
    items.addEventListener('input', calculateTotal);
    items.addEventListener('click', (event) => { if (event.target.classList.contains('remove-receive-row')) { event.target.closest('tr').remove(); calculateTotal(); } });
    form.addEventListener('submit', (event) => {
        if (!items.querySelector('tr')) { event.preventDefault(); window.alert('กรุณาเพิ่มรายการสินค้า'); return; }
        document.getElementById('preview-button').disabled = true;
    });
    updateSource();
}());
