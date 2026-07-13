document.addEventListener('DOMContentLoaded', function () {
    const openButton = document.getElementById(
        'openBulkCopyTierModal'
    );

    const modal = document.getElementById(
        'bulkCopyTierModal'
    );

    if (!openButton || !modal) {
        return;
    }

    openButton.addEventListener('click', function () {

        $('#bulkCopyTierModal').modal('show');

        loadBulkCopyData();

        updateTargetCount();

    });

    const targetContainer =
        document.getElementById('bulkCopyTargetList');

    const selectAllButton =
        document.getElementById('bulkCopySelectAll');

    const unselectAllButton =
        document.getElementById('bulkCopyUnselectAll');

    if (selectAllButton) {

        selectAllButton.addEventListener('click', function () {

            targetContainer
                .querySelectorAll('input[type="checkbox"]')
                .forEach(function (checkbox) {

                    checkbox.checked = true;

                    selectedTargetIds.add(
                        String(checkbox.value)
                    );

                });

            updateTargetCount();

        });

    }

    if (unselectAllButton) {

        unselectAllButton.addEventListener('click', function () {

            targetContainer
                .querySelectorAll('input[type="checkbox"]')
                .forEach(function (checkbox) {

                    checkbox.checked = false;

                    selectedTargetIds.delete(
                        String(checkbox.value)
                    );

                });

            updateTargetCount();

        });

    }

    document.addEventListener('change', function (event) {

        if (
            event.target.matches(
                '#bulkCopyTargetList input[type="checkbox"]'
            )
        ) {

            const targetId =
                String(event.target.value);

            if (event.target.checked) {
                selectedTargetIds.add(targetId);
            } else {
                selectedTargetIds.delete(targetId);
            }

            updateTargetCount();

        }

    });

    const categorySelect =
        document.getElementById('bulkCopyCategory');

    const sourceSelect =
        document.getElementById('bulkCopySourceUnit');

    if (categorySelect) {

        categorySelect.addEventListener('change', function () {

            renderTargetUnits();

        });

    }

    if (sourceSelect) {

        sourceSelect.addEventListener('change', function () {

            const sourceId =
                String(sourceSelect.value || '');

            if (sourceId !== '') {
                selectedTargetIds.delete(sourceId);
            }

            renderTargetUnits();

        });

    }

    let bulkCopyData = null;

    async function loadBulkCopyData() {

        if (bulkCopyData !== null) {
            return;
        }

        const response = await fetch(
            '/product-price-tiers/bulk-copy-data'
        );

        bulkCopyData = await response.json();

        renderCategories();

        renderSourceUnits();

        renderTargetUnits();

        console.log(
            'Bulk Copy Data',
            bulkCopyData
        );
    }

    function renderCategories() {

        const categorySelect =
            document.getElementById(
                'bulkCopyCategory'
            );

        if (!categorySelect) {
            return;
        }

        categorySelect.innerHTML =
            '<option value="">ทั้งหมด</option>';

        bulkCopyData.categories.forEach(function (category) {

            categorySelect.insertAdjacentHTML(
                'beforeend',
                `
            <option value="${category.id}">
                ${category.name}
            </option>
            `
            );

        });

    }

    function renderSourceUnits() {

        const sourceSelect =
            document.getElementById(
                'bulkCopySourceUnit'
            );

        if (!sourceSelect) {
            return;
        }

        sourceSelect.innerHTML =
            '<option value="">-- เลือกต้นทาง --</option>';

        bulkCopyData.product_units.forEach(function (unit) {

            const productName =
                unit.product.name;

            const unitName =
                unit.unit.name;

            sourceSelect.insertAdjacentHTML(
                'beforeend',
                `
            <option value="${unit.id}">
                ${productName}
                (${unitName})
                - Tier ${unit.price_tiers_count}
            </option>
            `
            );

        });

    }

    const selectedTargetIds = new Set();

    function renderTargetUnits() {

        if (!bulkCopyData || !targetContainer) {
            return;
        }

        const categorySelect =
            document.getElementById('bulkCopyCategory');

        const sourceSelect =
            document.getElementById('bulkCopySourceUnit');

        const selectedCategoryId =
            categorySelect?.value || '';

        const sourceProductUnitId =
            sourceSelect?.value || '';

        const filteredUnits =
            bulkCopyData.product_units.filter(function (productUnit) {

                const productCategoryId =
                    String(productUnit.product.category_id ?? '');

                const matchesCategory =
                    selectedCategoryId === '' ||
                    productCategoryId === String(selectedCategoryId);

                const isNotSource =
                    String(productUnit.id) !== String(sourceProductUnitId);

                return matchesCategory && isNotSource;

            });

        if (filteredUnits.length === 0) {

            targetContainer.innerHTML = `
            <div class="text-muted text-center py-3">
                ไม่พบ Product Unit สำหรับเลือกเป็นปลายทาง
            </div>
        `;

            updateTargetCount();

            return;
        }

        targetContainer.innerHTML = filteredUnits
            .map(function (productUnit) {

                const targetId =
                    String(productUnit.id);

                const productName =
                    productUnit.product?.name || '-';

                const unitName =
                    productUnit.unit?.name || '-';

                const tierCount =
                    productUnit.price_tiers_count || 0;

                const checked =
                    selectedTargetIds.has(targetId)
                        ? 'checked'
                        : '';

                return `
                <div class="custom-control custom-checkbox border-bottom py-2">
                    <input
                        type="checkbox"
                        class="custom-control-input bulk-copy-target-checkbox"
                        id="bulkCopyTarget${targetId}"
                        value="${targetId}"
                        ${checked}
                    >

                    <label
                        class="custom-control-label d-flex justify-content-between"
                        for="bulkCopyTarget${targetId}"
                    >
                        <span>
                            ${escapeHtml(productName)}
                            (${escapeHtml(unitName)})
                        </span>

                        <span class="badge badge-secondary">
                            Tier ${tierCount}
                        </span>
                    </label>
                </div>
            `;

            })
            .join('');

        updateTargetCount();

    }

    function escapeHtml(value) {

        const element =
            document.createElement('div');

        element.textContent =
            value === null || value === undefined
                ? ''
                : String(value);

        return element.innerHTML;

    }

    const copyButton =
    document.getElementById(
        'bulkCopyTierSubmit'
    );

if (copyButton) {

    copyButton.addEventListener(
        'click',
        async function () {

            const sourceProductUnitId =
                document.getElementById(
                    'bulkCopySourceUnit'
                ).value;

            if (!sourceProductUnitId) {

                alert('กรุณาเลือกต้นทาง');

                return;

            }

            const targetProductUnitIds =
                Array.from(
                    selectedTargetIds
                );

            if (
                targetProductUnitIds.length === 0
            ) {

                alert('กรุณาเลือกปลายทาง');

                return;

            }

            copyButton.disabled = true;

            try {

                const response =
                    await fetch(
                        '/product-price-tiers/bulk-copy',
                        {
                            method: 'POST',

                            headers: {
                                'Content-Type':
                                    'application/json',

                                'X-CSRF-TOKEN':
                                    document
                                        .querySelector(
                                            'meta[name="csrf-token"]'
                                        )
                                        .content,
                            },

                            body: JSON.stringify({
                                source_product_unit_id:
                                    sourceProductUnitId,

                                target_product_unit_ids:
                                    targetProductUnitIds,
                            }),
                        }
                    );

                const result =
                    await response.json();

                if (!response.ok) {

                    throw new Error(
                        result.message ??
                        'Bulk Copy ไม่สำเร็จ'
                    );

                }

                $('#bulkCopyTierModal')
                    .modal('hide');

                alert(result.message);

                window.location.reload();

            } catch (error) {

                alert(error.message);

            } finally {

                copyButton.disabled = false;

            }

        }
    );

}

    function updateTargetCount() {

        const checked = document.querySelectorAll(
            '#bulkCopyTargetList input[type="checkbox"]:checked'
        ).length;

        const countElement =
            document.getElementById('bulkCopyTargetCount');

        const previewElement =
            document.getElementById('bulkCopyPreview');

        if (countElement) {
            countElement.textContent = checked;
        }

        if (previewElement) {

            previewElement.innerHTML =
                'พร้อมคัดลอกไปยัง <strong>' +
                checked +
                '</strong> รายการ';

        }

    }
});
