document.addEventListener('DOMContentLoaded', () => {
    const drawer = document.getElementById('pricingDrawer');
    const form = document.getElementById('pricingForm');
    let productId = null;
    let product = null;

    const money = value => value === null || value === undefined || value === '' ? '-' : Number(value).toFixed(2);
    const statusLabel = { pending_review: 'รอทบทวน', unpriced: 'ยังไม่ตั้งราคา', normal: 'ปกติ', inactive: 'ไม่ใช้งาน' };

    function calculatePreview() {
        const cost = Number(product?.average_cost);
        const value = Number(document.getElementById('pricingValue').value);
        const method = document.getElementById('pricingMethod').value;
        if (!Number.isFinite(cost) || !Number.isFinite(value)) return null;
        const before = method === 'percentage' ? cost + (cost * value / 100) : method === 'fixed' ? cost + value : value;
        const final = method === 'manual' ? before : window.PricingRounding.roundPrice(before, document.getElementById('roundingDirection').value, document.getElementById('roundingUnit').value);
        const profit = final - cost;
        return { before, final, profit, percent: cost ? profit / cost * 100 : null };
    }

    function renderCalculation() {
        const preview = calculatePreview();
        if (!preview) {
            document.getElementById('drawerResult').textContent = 'ยังไม่มีต้นทุนเฉลี่ยเพียงพอสำหรับคำนวณราคา';
            document.getElementById('calculationDetails').textContent = '';
            return;
        }
        document.getElementById('drawerResult').innerHTML = `<strong>ราคาแนะนำ</strong><div class="h4">${money(preview.final)} บาท</div><strong>กำไรต่อหน่วย</strong><div>${money(preview.profit)} บาท (${preview.percent === null ? '-' : money(preview.percent)}%)</div>`;
        document.getElementById('calculationDetails').innerHTML = `ต้นทุนเฉลี่ย ${money(product.average_cost)} บาท<br>กำไรก่อนปัด ${money(preview.before - Number(product.average_cost))} บาท<br>ราคาก่อนปัด ${money(preview.before)} บาท<br>ราคาหลังปัด ${money(preview.final)} บาท<br>กำไรสุดท้าย ${money(preview.profit)} บาท (${preview.percent === null ? '-' : money(preview.percent)}%)`;
    }

    function updateMethodFields() {
        const manual = document.getElementById('pricingMethod').value === 'manual';
        document.getElementById('pricingValueLabel').textContent = manual ? 'ราคาขาย' : 'ค่า';
        document.getElementById('pricingValueSuffix').textContent = document.getElementById('pricingMethod').value === 'percentage' ? '%' : 'บาท';
        document.getElementById('roundingRow').classList.toggle('d-none', manual);
        renderCalculation();
    }

    async function openDrawer(id) {
        const response = await fetch(`/pricing-management/${id}`, { headers: { Accept: 'application/json' } });
        product = await response.json();
        productId = id;
        document.getElementById('drawerProductName').textContent = product.product_name;
        document.getElementById('drawerCategory').textContent = `หมวด: ${product.category_name || '-'}`;
        document.getElementById('drawerStatus').innerHTML = `<span class="badge badge-info">${statusLabel[product.status]}</span>`;
        document.getElementById('drawerCost').innerHTML = product.status === 'pending_review' ? `ต้นทุนเฉลี่ยเดิม <strong>${money(product.old_average_cost)} บาท</strong> &nbsp; ต้นทุนเฉลี่ยใหม่ <strong>${money(product.average_cost)} บาท</strong>` : product.average_cost === null ? 'ยังไม่มีต้นทุนเฉลี่ย' : `ต้นทุนเฉลี่ยล่าสุด <strong>${money(product.average_cost)} บาท</strong>`;
        document.getElementById('pricingMethod').value = product.pricing_method || 'percentage';
        document.getElementById('pricingValue').value = product.pricing_value || '';
        document.getElementById('roundingDirection').value = product.rounding_direction || 'up';
        window.PricingRounding.selectOption(document.getElementById('roundingUnit'), product.rounding_unit || '5');
        document.getElementById('drawerSave').disabled = product.status === 'inactive';
        updateMethodFields();
        drawer.classList.add('open');
        drawer.setAttribute('aria-hidden', 'false');
    }

    function closeDrawer() { drawer.classList.remove('open'); drawer.setAttribute('aria-hidden', 'true'); }

    document.querySelectorAll('.js-open-pricing').forEach(button => button.addEventListener('click', () => openDrawer(button.dataset.productId)));
    document.getElementById('drawerClose').addEventListener('click', closeDrawer);
    document.getElementById('drawerCancel').addEventListener('click', closeDrawer);
    document.getElementById('pricingMethod').addEventListener('change', updateMethodFields);
    ['pricingValue', 'roundingDirection', 'roundingUnit'].forEach(id => document.getElementById(id).addEventListener('input', renderCalculation));
    document.getElementById('roundingDirection').addEventListener('change', renderCalculation);
    document.getElementById('roundingUnit').addEventListener('change', renderCalculation);

    form.addEventListener('submit', async event => {
        event.preventDefault();
        const save = document.getElementById('drawerSave');
        const error = document.getElementById('drawerError');
        save.disabled = true;
        error.classList.add('d-none');
        try {
            const response = await fetch(`/pricing-management/${productId}`, { method: 'PUT', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, Accept: 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify(Object.fromEntries(new FormData(form))) });
            if (!response.ok) throw new Error('บันทึกข้อมูลไม่สำเร็จ กรุณาตรวจสอบข้อมูลอีกครั้ง');
            window.location.reload();
        } catch (err) {
            error.textContent = err.message;
            error.classList.remove('d-none');
            save.disabled = false;
        }
    });

    const search = document.getElementById('pricingSearchInput');
    const filter = document.getElementById('pricingFilterSelect');
    function filterRows() {
        const keyword = search.value.toLowerCase().trim();
        document.querySelectorAll('.pricing-product-row').forEach(row => row.classList.toggle('d-none', !(row.dataset.search.includes(keyword) && (!filter.value || row.dataset.status === filter.value))));
    }
    search.addEventListener('input', filterRows);
    filter.addEventListener('change', filterRows);
});
