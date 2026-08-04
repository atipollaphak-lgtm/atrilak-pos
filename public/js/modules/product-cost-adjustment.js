(function () {
    'use strict';

    const modal = document.getElementById('productCostModal');
    if (!modal) return;

    const form = document.getElementById('productCostModalForm');
    const baseAction = form.dataset.actionBase;
    const productId = document.getElementById('productCostProductId');
    const currentCost = document.getElementById('productCostCurrent');
    const newCost = document.getElementById('productCostNew');
    const selling = document.getElementById('productCostSelling');
    const profitBefore = document.getElementById('productCostProfitBefore');
    const profitAfter = document.getElementById('productCostProfitAfter');
    const productName = document.getElementById('productCostModalProductName');
    let selectedProduct = null;

    const money = (value) => Number(value || 0).toFixed(2);
    const profit = (sellingPrice, cost) => Number(sellingPrice || 0) - Number(cost || 0);

    const updateProfit = () => {
        if (!selectedProduct) return;

        profitBefore.textContent = money(profit(selectedProduct.selling_price, selectedProduct.cost_price));
        profitAfter.textContent = money(profit(selectedProduct.selling_price, newCost.value));
    };

    const open = (product) => {
        if (!product) return;

        selectedProduct = product;
        productId.value = product.id;
        form.action = `${baseAction}/${encodeURIComponent(product.id)}/cost`;
        currentCost.value = money(product.cost_price);
        newCost.value = money(product.cost_price);
        selling.textContent = money(product.selling_price);
        productName.textContent = `${product.name || 'สินค้า'} · ต้นทุน ${money(product.cost_price)}`;
        updateProfit();

        $('#productModal').modal('hide');
        $('#productCostModal').modal('show');
    };

    window.addEventListener('product-details-loaded', (event) => {
        selectedProduct = event.detail;
    });

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-open-cost-modal]');
        if (trigger) {
            event.preventDefault();
            open(selectedProduct);
        }
    });

    newCost.addEventListener('input', updateProfit);

    const validationProductId = productId.value || modal.dataset.productId;
    if (modal.dataset.validationErrors === '1' && validationProductId) {
        form.action = `${baseAction}/${encodeURIComponent(validationProductId)}/cost`;
        $('#productCostModal').modal('show');
    }
})();
