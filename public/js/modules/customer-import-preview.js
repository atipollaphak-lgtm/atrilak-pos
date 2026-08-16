(function () {
    const filter = document.getElementById('customer-import-status-filter');
    const rows = Array.from(document.querySelectorAll('[data-import-row]'));
    const checkboxes = Array.from(document.querySelectorAll('[data-import-select]'));
    const selectedCount = document.getElementById('customer-import-selected-count');
    const confirmCount = document.getElementById('customer-import-confirm-count');
    const confirmForm = document.getElementById('customer-import-confirm-form');
    const confirmButton = document.getElementById('customer-import-confirm-button');

    if (!filter || rows.length === 0) {
        return;
    }

    function updateRows() {
        const selected = filter.value;
        rows.forEach((row) => {
            row.hidden = selected !== 'all' && row.dataset.status !== selected;
        });
    }

    function updateCount() {
        if (selectedCount) {
            selectedCount.textContent = checkboxes.filter((checkbox) => checkbox.checked).length;
        }
        if (confirmCount) {
            confirmCount.textContent = checkboxes.filter((checkbox) => checkbox.checked).length;
        }
        if (confirmButton) {
            confirmButton.disabled = checkboxes.filter((checkbox) => checkbox.checked).length === 0;
        }
    }

    filter.addEventListener('change', updateRows);
    checkboxes.forEach((checkbox) => checkbox.addEventListener('change', updateCount));
    confirmForm?.addEventListener('submit', () => {
        if (confirmButton) {
            confirmButton.disabled = true;
            confirmButton.textContent = 'กำลังนำเข้า...';
        }
    });
    updateRows();
    updateCount();
})();
