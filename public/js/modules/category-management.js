(function () {
    'use strict';

    const modal = document.getElementById('categoryModal');
    if (!modal) return;

    const form = document.getElementById('categoryModalForm');
    const method = document.getElementById('categoryModalMethod');
    const name = document.getElementById('categoryModalName');
    const code = document.getElementById('categoryModalCode');
    const barcode = document.getElementById('categoryModalBarcode');
    const active = document.getElementById('categoryModalActive');
    const description = document.getElementById('categoryModalDescription');
    const rounding = document.getElementById('categoryModalRounding');
    const errors = document.getElementById('categoryModalErrors');
    const title = document.getElementById('categoryModalTitle');
    const orderSave = document.getElementById('category-order-save');
    const tableBody = document.getElementById('category-table-body');
    let editingId = null;
    let codeTouched = false;
    let draggedRow = null;

    const thaiCodeMap = { ก: 'K', ข: 'K', ค: 'K', ง: 'N', จ: 'J', ช: 'C', ซ: 'S', ด: 'D', ต: 'T', ถ: 'T', ท: 'T', น: 'N', บ: 'B', ป: 'P', ผ: 'P', พ: 'P', ฟ: 'F', ม: 'M', ย: 'Y', ร: 'R', ล: 'L', ว: 'W', ศ: 'S', ส: 'S', ห: 'H', อ: '', ฮ: 'H' };
    const generateCodePrefix = (value) => {
        const latin = value.match(/[A-Za-z]/g)?.join('').toUpperCase() || '';
        if (latin) return latin.slice(0, 3);
        const thai = [...value].map((character) => thaiCodeMap[character] || '').join('');
        return thai.slice(-3).toUpperCase();
    };
    const generateBarcodePrefix = () => {
        const used = [...document.querySelectorAll('[data-category-delete]')].map((element) => Number(element.dataset.productCount) >= 0 ? element.closest('tr')?.querySelectorAll('.badge-light')[1]?.textContent.trim() : '').filter((value) => /^\d{3}$/.test(value)).map(Number);
        for (let value = 101; value <= 999; value += 1) if (!used.includes(value)) return String(value);
        return '';
    };
    const showError = (message) => { errors.innerHTML = message; errors.classList.remove('d-none'); };
    const clearError = () => { errors.innerHTML = ''; errors.classList.add('d-none'); };
    const jsonHeaders = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };

    $(modal).on('show.bs.modal', function (event) {
        const trigger = event.relatedTarget;
        const category = trigger?.dataset.category ? JSON.parse(trigger.dataset.category) : null;
        editingId = category?.id || null;
        codeTouched = Boolean(editingId);
        form.action = editingId ? `/categories/${editingId}` : '/categories';
        method.value = editingId ? 'PUT' : 'POST';
        title.textContent = editingId ? 'แก้ไขหมวดหมู่' : 'เพิ่มหมวดหมู่';
        clearError();
        if (category) {
            name.value = category.name || '';
            code.value = category.code_prefix || '';
            barcode.value = category.barcode_prefix || '';
            active.checked = Boolean(category.active);
            description.value = category.description || '';
            rounding.value = category.rounding_override || '';
        } else {
            form.reset();
            method.value = 'POST';
            active.checked = true;
            barcode.value = generateBarcodePrefix();
        }
    });

    name.addEventListener('input', function () { if (!editingId && !codeTouched) code.value = generateCodePrefix(name.value); });
    code.addEventListener('input', function () { codeTouched = true; code.value = code.value.toUpperCase().replace(/[^A-Z]/g, ''); });
    barcode.addEventListener('input', function () { barcode.value = barcode.value.replace(/\D/g, '').slice(0, 3); });

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        clearError();
        const submit = document.getElementById('categoryModalSubmit');
        submit.disabled = true;
        submit.classList.add('disabled');
        try {
            const formData = new FormData(form);
            formData.set('active', active.checked ? '1' : '0');
            const response = await fetch(form.action, { method: 'POST', headers: jsonHeaders, body: formData });
            const payload = await response.json();
            if (!response.ok) {
                const messages = payload.errors ? Object.values(payload.errors).flat().join('<br>') : (payload.message || 'ตรวจสอบข้อมูลอีกครั้ง');
                showError(messages);
                return;
            }
            $('#categoryModal').modal('hide');
            await Swal.fire({ icon: 'success', title: 'บันทึกสำเร็จ', timer: 1200, showConfirmButton: false });
            window.location.reload();
        } catch (error) { showError('ไม่สามารถเชื่อมต่อระบบได้ กรุณาลองใหม่'); }
        finally { submit.disabled = false; submit.classList.remove('disabled'); }
    });

    document.getElementById('category-search').addEventListener('input', function () {
        const query = this.value.toLowerCase().trim();
        document.querySelectorAll('[data-category-row]').forEach((row) => { row.classList.toggle('d-none', query !== '' && !row.dataset.search.includes(query)); });
    });

    document.querySelectorAll('[data-category-delete]').forEach((button) => button.addEventListener('click', async function () {
        if (Number(button.dataset.productCount) > 0) { await Swal.fire({ icon: 'error', title: 'ลบไม่ได้', text: 'หมวดหมู่นี้มีสินค้าอยู่ จึงไม่สามารถลบได้' }); return; }
        const result = await Swal.fire({ icon: 'warning', title: 'ลบหมวดหมู่?', text: `ต้องการลบ ${button.dataset.name} หรือไม่`, showCancelButton: true, confirmButtonText: 'ลบ', cancelButtonText: 'ยกเลิก', confirmButtonColor: '#dc3545' });
        if (!result.isConfirmed) return;
        const response = await fetch(button.dataset.url, { method: 'DELETE', headers: { ...jsonHeaders, 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } });
        const payload = await response.json();
        if (!response.ok) { await Swal.fire({ icon: 'error', title: 'ลบไม่ได้', text: payload.message || 'เกิดข้อผิดพลาด' }); return; }
        await Swal.fire({ icon: 'success', title: 'ลบสำเร็จ', timer: 1200, showConfirmButton: false });
        window.location.reload();
    }));

    const categoryRows = () => [...document.querySelectorAll('[data-category-row]')];
    const syncOrderNumbers = () => categoryRows().forEach((row, index) => {
        const number = row.querySelector('.category-order-number');
        if (number) number.textContent = String(index + 1);
    });
    const markOrderChanged = () => {
        if (orderSave) orderSave.disabled = false;
    };

    categoryRows().forEach((row) => {
        row.addEventListener('dragstart', (event) => {
            draggedRow = row;
            row.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', row.dataset.categoryId);
        });
        row.addEventListener('dragover', (event) => {
            event.preventDefault();
            if (!draggedRow || draggedRow === row) return;
            const bounds = row.getBoundingClientRect();
            const insertBefore = event.clientY < bounds.top + bounds.height / 2;
            tableBody.insertBefore(draggedRow, insertBefore ? row : row.nextSibling);
            syncOrderNumbers();
            markOrderChanged();
        });
        row.addEventListener('dragend', () => {
            row.classList.remove('is-dragging');
            draggedRow = null;
        });
    });

    orderSave?.addEventListener('click', async () => {
        const categoryIds = categoryRows().map((row) => Number(row.dataset.categoryId));
        orderSave.disabled = true;
        try {
            const response = await fetch(orderSave.dataset.url, {
                method: 'PUT',
                headers: {
                    ...jsonHeaders,
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ category_ids: categoryIds }),
            });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.message || 'บันทึกลำดับไม่สำเร็จ');
            await Swal.fire({ icon: 'success', title: 'บันทึกลำดับแล้ว', timer: 1200, showConfirmButton: false });
            window.location.reload();
        } catch (error) {
            orderSave.disabled = false;
            await Swal.fire({ icon: 'error', title: 'บันทึกลำดับไม่สำเร็จ', text: error.message || 'กรุณาลองใหม่' });
        }
    });
}());
