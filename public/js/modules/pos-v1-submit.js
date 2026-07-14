(function bindPosV1Submit() {
    const form = document.getElementById("saleForm");
    const button = document.getElementById("btn-submit-sale-v1");
    const keyInput = document.getElementById("sale-idempotency-key");

    if (!form || !button || !keyInput) {
        return;
    }

    let isSubmitting = false;
    let pendingKey = null;

    form.addEventListener("submit", async function (event) {
        if (event.defaultPrevented) {
            return;
        }

        event.preventDefault();

        if (isSubmitting) {
            return;
        }

        if (!pendingKey) {
            pendingKey = crypto.randomUUID();
        }

        keyInput.value = pendingKey;
        isSubmitting = true;
        button.disabled = true;

        const invoiceWindow = window.open("", "_blank");

        try {
            const response = await fetch(form.action, {
                method: "POST",
                headers: {
                    "Accept": "application/json",
                    "X-CSRF-TOKEN": form.querySelector('input[name="_token"]').value
                },
                body: new FormData(form)
            });
            const data = await parseJsonResponse(response);

            if (!response.ok) {
                const error = new Error(data.message || "ไม่สามารถบันทึกการขายได้");
                error.status = response.status;
                throw error;
            }

            if (!data.success || !data.invoice_url) {
                throw new Error(data.message || "ไม่สามารถบันทึกการขายได้");
            }

            pendingKey = null;
            keyInput.value = "";

            if (invoiceWindow) {
                invoiceWindow.location.replace(data.invoice_url);
            } else {
                window.open(data.invoice_url, "_blank");
            }

            window.location.assign(form.dataset.successUrl);
        } catch (error) {
            if (invoiceWindow && !invoiceWindow.closed) {
                invoiceWindow.close();
            }

            if (error.status === 409) {
                pendingKey = null;
                keyInput.value = "";
            }

            alert(error.message || "เกิดข้อผิดพลาดระหว่างบันทึกการขาย");
            isSubmitting = false;
            button.disabled = false;
        }
    });

    async function parseJsonResponse(response) {
        const text = await response.text();

        try {
            return JSON.parse(text);
        } catch (error) {
            throw new Error("Backend ไม่ได้ส่ง JSON กลับมา");
        }
    }
})();
