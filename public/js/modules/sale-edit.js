(() => {
    const itemRows = document.getElementById('sale-items');
    const addRowButton = document.getElementById('addRow');

    if (!itemRows || !addRowButton) {
        return;
    }

    const calculateTotals = () => {
        let grandTotal = 0;

        itemRows.querySelectorAll('tr').forEach((row) => {
            const qty = Number.parseFloat(row.querySelector('.qty')?.value || 0);
            const price = Number.parseFloat(row.querySelector('.price')?.value || 0);
            const total = qty * price;

            row.querySelector('.line-total').textContent = total.toFixed(2);
            grandTotal += total;
        });

        document.getElementById('grand-total').textContent = grandTotal.toFixed(2);

        const deliveryFee = Number.parseFloat(document.getElementById('delivery_fee')?.value || 0);
        const discount = Number.parseFloat(document.getElementById('discount')?.value || 0);

        document.getElementById('net_total').value = (grandTotal + deliveryFee - discount).toFixed(2);
    };

    addRowButton.addEventListener('click', () => {
        const sourceRow = itemRows.querySelector('tr');

        if (!sourceRow) {
            return;
        }

        const clone = sourceRow.cloneNode(true);

        clone.querySelectorAll('input').forEach((input) => {
            input.value = '';
        });
        clone.querySelectorAll('.invalid-historical-option').forEach((option) => option.remove());
        clone.querySelectorAll('[data-inactive="1"]').forEach((option) => {
            option.disabled = true;
        });
        clone.querySelector('.product-select').value = '';
        clone.querySelector('.line-total').textContent = '0.00';

        itemRows.appendChild(clone);
    });

    document.addEventListener('click', (event) => {
        if (!event.target.classList.contains('remove-row')) {
            return;
        }

        if (itemRows.querySelectorAll('tr').length > 1) {
            event.target.closest('tr').remove();
            calculateTotals();
        }
    });

    document.addEventListener('input', (event) => {
        if (event.target.classList.contains('qty')
            || event.target.classList.contains('price')
            || event.target.id === 'delivery_fee'
            || event.target.id === 'discount') {
            calculateTotals();
        }
    });

    document.addEventListener('change', (event) => {
        if (!event.target.classList.contains('product-select')) {
            return;
        }

        const selectedOption = event.target.options[event.target.selectedIndex];
        const row = event.target.closest('tr');

        row.querySelector('.sale-item-id').value = '';
        row.querySelector('.product-unit-id').value = '';
        row.querySelector('.price').value = selectedOption?.dataset.price || 0;

        calculateTotals();
    });

    calculateTotals();
})();
