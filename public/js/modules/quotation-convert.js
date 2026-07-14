(function bindQuotationConvertGuard() {
    const form = document.getElementById("quotation-convert-form");
    const button = document.getElementById("quotation-convert-button");

    if (!form || !button) {
        return;
    }

    let isSubmitting = false;

    form.addEventListener("submit", function (event) {
        if (isSubmitting) {
            event.preventDefault();
            return;
        }

        isSubmitting = true;
        button.disabled = true;
    });
})();
