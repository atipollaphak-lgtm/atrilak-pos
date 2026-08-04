(function () {
    'use strict';

    function initializeResetForm() {
        var form = document.querySelector('[data-reset-form]');
        var modal = document.querySelector('[data-reset-modal]');

        if (!form || !modal) {
            return;
        }

        var acknowledgement = form.querySelector('[data-reset-acknowledged]');
        var confirmation = form.querySelector('[data-reset-confirmation]');
        var password = form.querySelector('[data-reset-password]');
        var submit = form.querySelector('[data-reset-submit]');
        var progress = form.querySelector('[data-reset-progress]');
        var expectedPhrase = form.getAttribute('data-reset-phrase') || '';
        var submitted = false;

        function syncSubmitState() {
            submit.disabled = !(
                acknowledgement.checked
                && confirmation.value === expectedPhrase
                && password.value.length > 0
            );
        }

        [acknowledgement, confirmation, password].forEach(function (input) {
            input.addEventListener('input', syncSubmitState);
            input.addEventListener('change', syncSubmitState);
        });

        form.addEventListener('submit', function (event) {
            if (submitted) {
                event.preventDefault();
                return;
            }

            submitted = true;
            submit.disabled = true;
            submit.textContent = 'กำลังดำเนินการ...';
            progress.classList.remove('d-none');
        });

        syncSubmitState();

        if (modal.getAttribute('data-reset-auto-open') === '1' && window.jQuery) {
            window.jQuery(modal).modal({
                backdrop: 'static',
                keyboard: false,
                show: true,
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeResetForm);
    } else {
        initializeResetForm();
    }
}());
