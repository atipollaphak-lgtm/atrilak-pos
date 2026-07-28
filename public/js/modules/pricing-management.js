document.addEventListener('DOMContentLoaded', () => {
    const drawer = document.getElementById('pricingDrawer');
    const form = document.getElementById('pricingForm');
    const method = document.getElementById('pricingMethod');
    const value = document.getElementById('pricingValue');
    const direction = document.getElementById('roundingDirection');
    const unit = document.getElementById('roundingUnit');
    let productId = null;
    let product = null;

    const money = input => input === null || input === undefined || input === '' ? '-' : Number(input).toFixed(2);
    const statusLabel = { pending_review: 'รอทบทวน', unpriced: 'ยังไม่ตั้งราคา', normal: 'ปกติ', inactive: 'ไม่ใช้งาน' };
    const csrf = () => document.querySelector('meta[name="csrf-token"]').content;

    function calculatePreview() {
        const cost = Number(product?.average_cost);
        const pricingValue = Number(value.value);
        if (!Number.isFinite(cost) || !Number.isFinite(pricingValue)) return null;
        const currentMethod = method.value === 'category' ? product?.category_rule?.pricing_method : method.value;
        const before = currentMethod === 'percentage' ? cost + (cost * pricingValue / 100) : cost + pricingValue;
        const final = window.PricingRounding.roundPrice(before, direction.value, unit.value);
        const profit = final - cost;
        return { before, final, profit, percent: cost ? profit / cost * 100 : null };
    }

    function renderCalculation() {
        const preview = method.value === 'manual' ? (() => {
            const cost = Number(product?.average_cost);
            const final = Number(value.value);
            if (!Number.isFinite(cost) || !Number.isFinite(final)) return null;
            const profit = final - cost;
            return { before: final, final, profit, percent: cost ? profit / cost * 100 : null };
        })() : calculatePreview();

        if (!preview) {
            document.getElementById('drawerResult').textContent = 'ยังไม่มีต้นทุนเฉลี่ยเพียงพอสำหรับคำนวณราคา';
            document.getElementById('calculationDetails').textContent = '';
            return;
        }

        document.getElementById('drawerResult').innerHTML = `<strong>ราคาแนะนำ</strong><div class="h4">${money(preview.final)} บาท</div><strong>กำไรต่อหน่วย</strong><div>${money(preview.profit)} บาท (${preview.percent === null ? '-' : money(preview.percent)}%)</div>`;
        document.getElementById('calculationDetails').innerHTML = `ต้นทุนเฉลี่ย ${money(product.average_cost)} บาท<br>กำไรก่อนปัด ${money(preview.before - Number(product.average_cost))} บาท<br>ราคาก่อนปัด ${money(preview.before)} บาท<br>ราคาหลังปัด ${money(preview.final)} บาท<br>กำไรสุดท้าย ${money(preview.profit)} บาท (${preview.percent === null ? '-' : money(preview.percent)}%)`;
    }

    function updateMethodFields() {
        const selected = method.value;
        const manual = selected === 'manual';
        const category = selected === 'category';
        document.getElementById('pricingValueLabel').textContent = manual ? 'ราคาขาย' : 'ค่า';
        document.getElementById('pricingValueSuffix').textContent = selected === 'percentage' || (category && product?.category_rule?.pricing_method === 'percentage') ? '%' : 'บาท';
        document.getElementById('roundingRow').classList.toggle('d-none', manual);
        value.readOnly = category;
        direction.disabled = category;
        unit.disabled = category;

        const info = document.getElementById('categoryPricingInfo');
        if (category) {
            const rule = product?.category_rule;
            info.classList.remove('d-none');
            if (!product?.category_rule_available || !rule) {
                info.className = 'alert alert-danger';
                info.textContent = 'หมวดนี้ยังไม่ได้ตั้งค่าราคา ไม่สามารถบันทึกการใช้กฎหมวดได้';
                document.getElementById('drawerSave').disabled = true;
            } else {
                info.className = 'alert alert-info';
                info.textContent = `หมวด ${product.category_name || '-'}: ${rule.pricing_method === 'percentage' ? '+' : '+'}${money(rule.pricing_value)}${rule.pricing_method === 'percentage' ? '%' : ' บาท'} | ${rule.rounding_direction || '-'} ${rule.rounding_unit || '-'} บาท`;
                document.getElementById('drawerSave').disabled = product.status === 'inactive';
            }
        } else {
            info.classList.add('d-none');
            document.getElementById('drawerSave').disabled = product?.status === 'inactive';
        }
        renderCalculation();
    }

    async function openDrawer(id) {
        const response = await fetch(`/pricing-management/${id}`, { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('โหลดข้อมูลราคาไม่สำเร็จ');
        product = await response.json();
        productId = id;
        document.getElementById('drawerProductName').textContent = product.product_name;
        document.getElementById('drawerCategory').textContent = `หมวด: ${product.category_name || '-'}`;
        document.getElementById('drawerStatus').innerHTML = `<span class="badge badge-info">${statusLabel[product.status]}</span>`;
        document.getElementById('drawerCost').innerHTML = product.status === 'pending_review' ? `ต้นทุนเฉลี่ยเดิม <strong>${money(product.old_average_cost)} บาท</strong> &nbsp; ต้นทุนเฉลี่ยใหม่ <strong>${money(product.average_cost)} บาท</strong>` : product.average_cost === null ? 'ยังไม่มีต้นทุนเฉลี่ย' : `ต้นทุนเฉลี่ยล่าสุด <strong>${money(product.average_cost)} บาท</strong>`;
        method.value = product.pricing_source === 'category' ? 'category' : product.pricing_method || 'percentage';
        value.value = product.pricing_value || '';
        direction.value = product.rounding_direction || 'up';
        window.PricingRounding.selectOption(unit, product.rounding_unit || '5');
        updateMethodFields();
        drawer.classList.add('open');
        drawer.setAttribute('aria-hidden', 'false');
    }

    function closeDrawer() {
        drawer.classList.remove('open');
        drawer.setAttribute('aria-hidden', 'true');
    }

    document.querySelectorAll('.js-open-pricing').forEach(button => button.addEventListener('click', () => openDrawer(button.dataset.productId).catch(error => alert(error.message))));
    document.getElementById('drawerClose').addEventListener('click', closeDrawer);
    document.getElementById('drawerCancel').addEventListener('click', closeDrawer);
    method.addEventListener('change', updateMethodFields);
    value.addEventListener('input', renderCalculation);
    direction.addEventListener('change', renderCalculation);
    unit.addEventListener('change', renderCalculation);

    form.addEventListener('submit', async event => {
        event.preventDefault();
        const save = document.getElementById('drawerSave');
        const error = document.getElementById('drawerError');
        save.disabled = true;
        error.classList.add('d-none');
        try {
            const payload = Object.fromEntries(new FormData(form));
            if (method.value === 'category' && product?.category_rule) {
                payload.pricing_value = product.category_rule.pricing_value;
                payload.rounding_direction = product.category_rule.rounding_direction;
                payload.rounding_unit = product.category_rule.rounding_unit;
            }
            const response = await fetch(`/pricing-management/${productId}`, { method: 'PUT', headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
            if (!response.ok) {
                const body = await response.json().catch(() => ({}));
                throw new Error(body.message || 'บันทึกข้อมูลไม่สำเร็จ');
            }
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

    const rulesButton = document.getElementById('categoryRulesButton');
    const rulesModal = document.getElementById('categoryRulesModal');
    const rulesForm = document.getElementById('categoryRuleForm');
    const rulesError = document.getElementById('categoryRulesError');
    const rulesTable = document.querySelector('#categoryRulesTable tbody');

    const escapeHtml = text => String(text ?? '').replace(/[&<>'"]/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[char]));
    function resetRuleForm() {
        rulesForm.reset();
        document.getElementById('categoryRuleId').value = '';
        document.getElementById('categoryRuleActive').checked = true;
    }

    function renderRules(rules) {
        rulesTable.innerHTML = rules.map(item => {
            const rule = item.rule;
            const ruleText = rule ? `${rule.pricing_method === 'percentage' ? '+' : '+'}${money(rule.pricing_value)}${rule.pricing_method === 'percentage' ? '%' : ' บาท'}` : '-';
            return `<tr><td>${escapeHtml(item.name)}</td><td>${ruleText}</td><td>${item.total_products}</td><td>${item.category_products}</td><td>${rule?.active ? 'เปิดใช้งาน' : 'ยังไม่ตั้งค่า'}</td><td>${rule ? `<button class="btn btn-sm btn-outline-primary js-edit-category-rule" data-rule="${escapeHtml(JSON.stringify({ ...rule, category_id: item.id }))}">แก้ไข</button> <button class="btn btn-sm btn-outline-danger js-disable-category-rule" data-id="${rule.id}">ปิด</button>` : ''}</td></tr>`;
        }).join('');
        rulesTable.querySelectorAll('.js-edit-category-rule').forEach(button => button.addEventListener('click', () => {
            const rule = JSON.parse(button.dataset.rule);
            document.getElementById('categoryRuleId').value = rule.id;
            document.getElementById('categoryRuleCategory').value = rule.category_id;
            document.getElementById('categoryRuleCategory').disabled = true;
            document.getElementById('categoryRuleMethod').value = rule.pricing_method;
            document.getElementById('categoryRuleValue').value = rule.pricing_value;
            document.getElementById('categoryRuleDirection').value = rule.rounding_direction || 'up';
            window.PricingRounding.selectOption(document.getElementById('categoryRuleUnit'), rule.rounding_unit || '5');
            document.getElementById('categoryRuleActive').checked = Boolean(rule.active);
        }));
        rulesTable.querySelectorAll('.js-disable-category-rule').forEach(button => button.addEventListener('click', async () => {
            const response = await fetch(`/pricing-management/category-rules/${button.dataset.id}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' } });
            if (!response.ok) {
                const body = await response.json().catch(() => ({}));
                rulesError.textContent = body.message || 'ปิดกฎไม่สำเร็จ';
                rulesError.classList.remove('d-none');
                return;
            }
            loadRules();
        }));
    }

    async function loadRules() {
        const response = await fetch('/pricing-management/category-rules', { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('โหลดกฎราคาตามหมวดไม่สำเร็จ');
        renderRules(await response.json());
    }

    rulesButton.addEventListener('click', async () => {
        resetRuleForm();
        document.getElementById('categoryRuleCategory').disabled = false;
        rulesError.classList.add('d-none');
        window.$(rulesModal).modal('show');
        try { await loadRules(); } catch (error) { rulesError.textContent = error.message; rulesError.classList.remove('d-none'); }
    });
    document.getElementById('categoryRuleReset').addEventListener('click', () => { resetRuleForm(); document.getElementById('categoryRuleCategory').disabled = false; });
    rulesForm.addEventListener('submit', async event => {
        event.preventDefault();
        const id = document.getElementById('categoryRuleId').value;
        const payload = Object.fromEntries(new FormData(rulesForm));
        payload.active = document.getElementById('categoryRuleActive').checked;
        if (id) payload.category_id = document.getElementById('categoryRuleCategory').value;
        const response = await fetch(id ? `/pricing-management/category-rules/${id}` : '/pricing-management/category-rules', { method: id ? 'PUT' : 'POST', headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
        if (!response.ok) {
            const body = await response.json().catch(() => ({}));
            rulesError.textContent = body.message || 'บันทึกกฎไม่สำเร็จ';
            rulesError.classList.remove('d-none');
            return;
        }
        resetRuleForm();
        document.getElementById('categoryRuleCategory').disabled = false;
        await loadRules();
    });
});
