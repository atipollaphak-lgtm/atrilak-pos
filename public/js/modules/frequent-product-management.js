(function () {
    'use strict';

    const page = document.getElementById('frequent-products-page');
    if (!page) return;

    const orderSave = document.getElementById('frequent-order-save');
    const tableBody = document.getElementById('frequent-product-table-body');
    const search = document.getElementById('frequent-product-search');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    let draggedRow = null;

    const rows = () => [...document.querySelectorAll('[data-frequent-row]')];
    const syncOrderNumbers = () => rows().forEach((row, index) => {
        const number = row.querySelector('.frequent-order-number');
        if (number) number.textContent = String(index + 1);
    });
    const showError = async (message) => {
        if (window.Swal) {
            await window.Swal.fire({ icon: 'error', title: 'ดำเนินการไม่สำเร็จ', text: message });
        } else {
            window.alert(message);
        }
    };
    const request = async (url, method, body) => {
        const response = await fetch(url, {
            method,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: body ? JSON.stringify(body) : undefined,
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(payload.message || 'กรุณาลองใหม่');
        return payload;
    };

    rows().forEach((row) => {
        row.addEventListener('dragstart', (event) => {
            draggedRow = row;
            row.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', row.dataset.frequentId);
        });
        row.addEventListener('dragover', (event) => {
            event.preventDefault();
            if (!draggedRow || draggedRow === row) return;
            const bounds = row.getBoundingClientRect();
            const insertBefore = event.clientY < bounds.top + bounds.height / 2;
            tableBody.insertBefore(draggedRow, insertBefore ? row : row.nextSibling);
            syncOrderNumbers();
            orderSave.disabled = false;
        });
        row.addEventListener('dragend', () => {
            row.classList.remove('is-dragging');
            draggedRow = null;
        });
    });

    document.querySelectorAll('[data-frequent-pin]').forEach((button) => {
        button.addEventListener('click', async () => {
            button.disabled = true;
            try {
                await request(button.dataset.url, 'POST');
                window.location.reload();
            } catch (error) {
                button.disabled = false;
                await showError(error.message);
            }
        });
    });

    document.querySelectorAll('[data-frequent-unpin]').forEach((button) => {
        button.addEventListener('click', async () => {
            const confirmed = window.Swal
                ? (await window.Swal.fire({
                    icon: 'question',
                    title: 'นำสินค้าออกจากรายการ?',
                    showCancelButton: true,
                    confirmButtonText: 'นำออก',
                    cancelButtonText: 'ยกเลิก',
                })).isConfirmed
                : window.confirm('นำสินค้าออกจากรายการ?');
            if (!confirmed) return;

            button.disabled = true;
            try {
                await request(button.dataset.url, 'DELETE');
                window.location.reload();
            } catch (error) {
                button.disabled = false;
                await showError(error.message);
            }
        });
    });

    orderSave?.addEventListener('click', async () => {
        orderSave.disabled = true;
        try {
            await request(page.dataset.orderUrl, 'PUT', {
                frequent_product_ids: rows().map((row) => Number(row.dataset.frequentId)),
            });
            window.location.reload();
        } catch (error) {
            orderSave.disabled = false;
            await showError(error.message);
        }
    });

    search?.addEventListener('input', () => {
        const query = search.value.trim().toLowerCase();
        document.querySelectorAll('[data-product-row]').forEach((row) => {
            row.classList.toggle('d-none', query !== '' && !row.dataset.productSearch.includes(query));
        });
    });
}());
