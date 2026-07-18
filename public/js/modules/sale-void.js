document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-void-sale-form]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.submitting === '1') {
                event.preventDefault();

                return;
            }

            form.dataset.submitting = '1';
            form.querySelectorAll('button[type="submit"]').forEach((button) => {
                button.disabled = true;
            });
        });
    });
});
