(function bindPosV1Submit() {
    const form = document.getElementById("saleForm");
    const button = document.getElementById("btn-submit-sale-v1");
    const keyInput = document.getElementById("sale-idempotency-key");

    if (!form || !button || !keyInput) {
        return;
    }

    const pendingIntent = SaleIntentStorage.createManager({
        storageKey: "atrilak.pos.v1.pending-sale.v1"
    });
    const submissionGuard = SaleIntentStorage.createSubmissionGuard();

    form.addEventListener("submit", async function (event) {
        if (event.defaultPrevented) {
            return;
        }

        event.preventDefault();

        if (!submissionGuard.start()) {
            return;
        }

        button.disabled = true;

        const invoiceWindow = window.open("", "_blank");
        let intent = null;

        try {
            intent = await pendingIntent.keyFor(
                SaleIntentStorage.formDataPayload(new FormData(form))
            );
            keyInput.value = intent.key;

            const response = await fetch(form.action, {
                method: "POST",
                headers: {
                    "Accept": "application/json",
                    "X-CSRF-TOKEN": form.querySelector('input[name="_token"]').value
                },
                body: new FormData(form)
            });
            let data;

            try {
                data = await parseJsonResponse(response);
            } catch (error) {
                error.status = response.status;
                throw error;
            }

            if (!response.ok) {
                const error = new Error(data.message || "ไม่สามารถบันทึกการขายได้");
                error.status = response.status;
                throw error;
            }

            if (!data.success || !data.invoice_url) {
                throw new Error(data.message || "ไม่สามารถบันทึกการขายได้");
            }

            pendingIntent.clear(intent.key);
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

            if (SaleIntentStorage.isDefinitiveClientError(error.status)) {
                pendingIntent.clear(intent?.key);
                keyInput.value = "";
            }

            alert(error.message || "เกิดข้อผิดพลาดระหว่างบันทึกการขาย");
            submissionGuard.release();
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
