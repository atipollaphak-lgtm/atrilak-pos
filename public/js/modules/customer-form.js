(function () {
    const form = document.querySelector('[data-customer-form]');
    if (!form) return;

    const phone = form.querySelector('#customer-phone');
    const receiver = form.querySelector('#receiver-phone');
    const useCustomerPhone = form.querySelector('#use-customer-phone');

    const syncReceiverPhone = function () {
        if (!phone || !receiver || !useCustomerPhone) return;
        receiver.readOnly = useCustomerPhone.checked;
        if (useCustomerPhone.checked) receiver.value = phone.value;
    };

    useCustomerPhone?.addEventListener('change', syncReceiverPhone);
    phone?.addEventListener('input', function () {
        if (useCustomerPhone?.checked) receiver.value = phone.value;
    });
    form.addEventListener('submit', function () {
        const button = form.querySelector('button[type="submit"]');
        if (button) {
            button.disabled = true;
            button.dataset.originalText = button.textContent;
            button.textContent = 'กำลังบันทึก...';
        }
    });
    document.querySelectorAll('form[data-confirm]').forEach(function (confirmForm) {
        confirmForm.addEventListener('submit', function (event) {
            if (!window.confirm(confirmForm.dataset.confirm)) event.preventDefault();
        });
    });
    syncReceiverPhone();
})();
