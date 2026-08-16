(function () {
    const filter = document.getElementById('customer-import-status-filter');
    const rows = Array.from(document.querySelectorAll('[data-import-row]'));
    const checkboxes = Array.from(document.querySelectorAll('[data-import-select]'));
    const selectedCount = document.getElementById('customer-import-selected-count');

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
    }

    filter.addEventListener('change', updateRows);
    checkboxes.forEach((checkbox) => checkbox.addEventListener('change', updateCount));
    updateRows();
    updateCount();
})();
