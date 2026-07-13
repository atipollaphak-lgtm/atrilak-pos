document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('pricingSearchInput');
    const filterSelect = document.getElementById('pricingFilterSelect');
    const rows = document.querySelectorAll('.pricing-product-row');
    const summaryCards = document.querySelectorAll('.pricing-summary-card');
    const bulkPreviewButton = document.getElementById('btnBulkPreview');

    function filterRows() {
        const keyword = (searchInput?.value || '').toLowerCase().trim();
        const filter = filterSelect?.value || '';

        rows.forEach(function (row) {
            const productName = row.dataset.productName || '';
            const productId = row.dataset.productId || '';

            const matchSearch =
                productName.includes(keyword) ||
                productId.includes(keyword);

            let matchFilter = true;

            if (filter === 'changed') {
                matchFilter = row.dataset.changed === '1';
            }

            if (filter === 'locked') {
                matchFilter = row.dataset.locked === '1';
            }

            if (filter === 'auto_off') {
                matchFilter = row.dataset.autoOff === '1';
            }

            if (filter === 'override') {
                matchFilter = row.dataset.override === '1';
            }

            if (filter === 'auto_pricing') {
                matchFilter = row.dataset.autoPricing === '1';
            }

            if (matchSearch && matchFilter) {
                row.classList.remove('pricing-hidden');
            } else {
                row.classList.add('pricing-hidden');
            }
        });
    }

    function initSearch() {

        if (searchInput) {
            searchInput.addEventListener('input', filterRows);
        }

        if (filterSelect) {
            filterSelect.addEventListener('change', filterRows);
        }

    }

    initSearch();

    function initSummaryCards() {

        summaryCards.forEach(function (card) {

            card.addEventListener('click', function () {

                const filter = card.dataset.filter || '';

                if (filterSelect) {
                    filterSelect.value = filter;
                }

                filterRows();

            });

        });

    }

    initSummaryCards();

    function showPreviewModal(data) {

        document.getElementById('previewTotal').textContent =
            data.total;

        document.getElementById('previewChanged').textContent =
            data.changed;

        document.getElementById('previewLocked').textContent =
            data.locked;

        document.getElementById('previewAutoOff').textContent =
            data.auto_off;

        document.getElementById('previewReady').textContent =
            data.ready_to_apply;

        $('#bulkPreviewModal').modal('show');

    }

    function loadPreview() {

        fetch('/pricing-management/preview-all', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document
                    .querySelector('meta[name="csrf-token"]')
                    .content,
                'Accept': 'application/json'
            }
        })
            .then(response => response.json())
            .then(data => {

                showPreviewModal(data);

            });

    }

    function initBulkPreview() {

        if (bulkPreviewButton) {

            bulkPreviewButton.addEventListener('click', function () {

                loadPreview();

            });

        }

    }

        initBulkPreview();

    function initCategoryPricingPreview() {
        const categorySelect = document.getElementById('categoryPricingCategoryId');
        const previewButton = document.getElementById('categoryPricingPreviewBtn');
        const previewBox = document.getElementById('categoryPricingPreviewBox');
        const summaryBox = document.getElementById('categoryPricingSummary');
        const rowsBox = document.getElementById('categoryPricingPreviewRows');

        if (!categorySelect || !previewButton || !previewBox || !summaryBox || !rowsBox) {
            return;
        }

        function formatMoney(value) {
            return Number(value || 0).toLocaleString('th-TH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function renderStatus(item) {
            if (item.price_lock) {
                return '<span class="badge badge-secondary">Locked</span>';
            }

            if (!item.auto_price_enabled) {
                return '<span class="badge badge-warning">Auto Off</span>';
            }

            if (!item.changed) {
                return '<span class="badge badge-light">No Change</span>';
            }

            return '<span class="badge badge-success">Ready</span>';
        }

        previewButton.addEventListener('click', function () {
            const categoryId = categorySelect.value;

            if (!categoryId) {
                alert('กรุณาเลือกหมวดสินค้าก่อน');
                return;
            }

            previewBox.classList.remove('d-none');
            summaryBox.textContent = 'กำลังโหลด Preview...';
            rowsBox.innerHTML = '';

            const formData = new FormData();
            formData.append('category_id', categoryId);

            fetch('/pricing-management/preview-category', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document
                        .querySelector('meta[name="csrf-token"]')
                        .content,
                    'Accept': 'application/json'
                },
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    const summary = data.summary || {};
                    const items = data.items || [];

                    summaryBox.textContent =
                        'ทั้งหมด: ' + (summary.total || 0)
                        + ' | เปลี่ยนราคา: ' + (summary.changed || 0)
                        + ' | Lock: ' + (summary.locked || 0)
                        + ' | Auto Off: ' + (summary.auto_off || 0)
                        + ' | พร้อม Apply: ' + (summary.ready_to_apply || 0);

                    if (!items.length) {
                        rowsBox.innerHTML =
                            '<tr><td colspan="5" class="text-center text-muted">ไม่พบสินค้าในหมวดนี้</td></tr>';
                        return;
                    }

                    rowsBox.innerHTML = items.map(function (item) {
                        return ''
                            + '<tr>'
                            + '<td>' + (item.product_name || ('#' + item.product_id)) + '</td>'
                            + '<td class="text-right">' + formatMoney(item.average_cost) + '</td>'
                            + '<td class="text-right">' + formatMoney(item.old_price) + '</td>'
                            + '<td class="text-right">' + formatMoney(item.final_price) + '</td>'
                            + '<td class="text-center">' + renderStatus(item) + '</td>'
                            + '</tr>';
                    }).join('');
                })
                .catch(() => {
                    summaryBox.textContent = 'โหลด Preview ไม่สำเร็จ';
                });
        });
    }

    initCategoryPricingPreview();
});
