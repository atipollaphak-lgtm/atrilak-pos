(() => {
    const itemRows = document.getElementById('sale-items');
    const addRowButton = document.getElementById('addRow');
    const form = document.getElementById('sale-edit-form');
    const paymentFields = {
        payment_method: document.getElementById('sale-payment-method'),
        cash_amount: document.getElementById('sale-cash-amount'),
        promptpay_amount: document.getElementById('sale-promptpay-amount'),
        received_amount: document.getElementById('sale-received-amount')
    };
    const hasPaymentFields = Object.values(paymentFields).every(Boolean);
    let paymentController = null;

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
        clone.querySelector('.price-action').value = 'preserve';
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
        if (event.target.classList.contains('price')) {
            const row = event.target.closest('tr');
            const priceAction = row?.querySelector('.price-action');

            if (priceAction) {
                priceAction.value = 'override';
            }
        }

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
        row.querySelector('.price-action').value = 'system';

        calculateTotals();
    });

    document.addEventListener('click', (event) => {
        if (!event.target.classList.contains('restore-system-price')) {
            return;
        }

        const row = event.target.closest('tr');
        const selectedOption = row?.querySelector('.product-select')?.selectedOptions[0];

        if (!row || !selectedOption?.value) {
            return;
        }

        row.querySelector('.price').value = selectedOption.dataset.price || 0;
        row.querySelector('.price-action').value = 'system';
        calculateTotals();
    });

    if (hasPaymentFields && window.PosPayment) {
        paymentController = window.PosPayment.createController({
            getTotal: () => document.getElementById('net_total')?.value || '0.00',
            getInitialPayment: () => ({
                payment_method: paymentFields.payment_method.value,
                cash_amount: paymentFields.cash_amount.value,
                promptpay_amount: paymentFields.promptpay_amount.value,
                received_amount: paymentFields.received_amount.value
            }),
            onConfirm: async (payment) => {
                const payload = window.PosPayment.payload(payment);

                Object.entries(payload).forEach(([field, value]) => {
                    paymentFields[field].value = value;
                });
                form.dataset.paymentConfirmed = '1';
                form.requestSubmit();
            }
        });
    }

    form?.addEventListener('submit', (event) => {
        if (!form.checkValidity()) {
            return;
        }

        if (form.dataset.submitting === '1') {
            event.preventDefault();

            return;
        }

        if (paymentController && form.dataset.paymentConfirmed !== '1') {
            event.preventDefault();
            paymentController.open();

            return;
        }

        form.dataset.submitting = '1';
        form.querySelectorAll('button[type="submit"]').forEach((button) => {
            button.disabled = true;
        });
    });

    window.addEventListener('pageshow', () => {
        if (!form) {
            return;
        }

        delete form.dataset.submitting;
        delete form.dataset.paymentConfirmed;
        form.querySelectorAll('button[type="submit"]').forEach((button) => {
            button.disabled = false;
        });
    });

    calculateTotals();
})();
