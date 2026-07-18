(function exposePosPayment(global) {
    function moneyToCents(value, label, allowBlank) {
        const text = String(value ?? "")
            .trim()
            .replaceAll(",", "");

        if (text === "" && allowBlank) {
            return null;
        }

        if (!/^\d+(?:\.\d{1,2})?$/.test(text)) {
            throw new Error(`${label}ต้องเป็นจำนวนเงินที่มีทศนิยมไม่เกิน 2 ตำแหน่ง`);
        }

        const [whole, decimal = ""] = text.split(".");

        return BigInt(whole) * 100n + BigInt(decimal.padEnd(2, "0"));
    }

    function centsToMoney(value) {
        const whole = value / 100n;
        const decimal = String(value % 100n).padStart(2, "0");

        return `${whole}.${decimal}`;
    }

    function resolve(method, totalValue, cashPortionValue, receivedValue) {
        const total = moneyToCents(totalValue, "ยอดสุทธิ", false);

        if (method === "cash") {
            const received = moneyToCents(receivedValue, "รับเงินสด", false);

            if (received < total) {
                throw new Error("รับเงินสดต้องไม่น้อยกว่ายอดเงินสดที่ใช้ชำระ");
            }

            return result(method, total, 0n, received, received - total);
        }

        if (method === "promptpay") {
            const received = moneyToCents(receivedValue, "รับเงินสด", true);

            if (received !== null && received !== 0n) {
                throw new Error("การชำระพร้อมเพย์ต้องไม่มีการรับเงินสด");
            }

            return result(method, 0n, total, 0n, 0n);
        }

        if (method === "mixed") {
            const cash = moneyToCents(cashPortionValue, "เงินสดที่ใช้ชำระ", false);
            const received = moneyToCents(receivedValue, "รับเงินสด", false);

            if (total === 0n) {
                throw new Error("ยอดสุทธิ 0.00 ไม่สามารถชำระแบบเงินสด + พร้อมเพย์ได้");
            }

            if (cash <= 0n || cash >= total) {
                throw new Error("เงินสดที่ใช้ชำระต้องมากกว่า 0.00 และน้อยกว่ายอดสุทธิ");
            }

            if (received < cash) {
                throw new Error("รับเงินสดต้องไม่น้อยกว่าเงินสดที่ใช้ชำระ");
            }

            return result(method, cash, total - cash, received, received - cash);
        }

        throw new Error("วิธีชำระเงินไม่ถูกต้อง");
    }

    function result(method, cash, promptpay, received, change) {
        return {
            payment_method: method,
            cash_amount: centsToMoney(cash),
            promptpay_amount: centsToMoney(promptpay),
            received_amount: centsToMoney(received),
            change_amount: centsToMoney(change)
        };
    }

    function payload(payment) {
        return {
            payment_method: payment.payment_method,
            cash_amount: payment.cash_amount,
            promptpay_amount: payment.promptpay_amount,
            received_amount: payment.received_amount
        };
    }

    function createController(options) {
        const elements = {
            modal: document.getElementById("paymentModal"),
            total: document.getElementById("payment-total"),
            method: document.getElementById("payment-method"),
            cashSummary: document.getElementById("payment-cash-summary"),
            cashAmount: document.getElementById("payment-cash-amount"),
            mixedCashGroup: document.getElementById("payment-mixed-cash-group"),
            mixedCash: document.getElementById("payment-mixed-cash"),
            promptpayGroup: document.getElementById("payment-promptpay-group"),
            promptpayAmount: document.getElementById("payment-promptpay-amount"),
            receivedGroup: document.getElementById("payment-received-group"),
            received: document.getElementById("payment-received"),
            change: document.getElementById("payment-change"),
            error: document.getElementById("payment-error"),
            confirm: document.getElementById("btn-confirm-payment")
        };
        let openedTotal = null;
        let confirming = false;

        if (Object.values(elements).some(element => !element)) {
            throw new Error("ไม่พบส่วนรับชำระเงินบนหน้าขาย");
        }

        function canonicalTotal() {
            return centsToMoney(moneyToCents(
                options.getTotal(),
                "ยอดสุทธิ",
                false
            ));
        }

        function show(element, visible) {
            element.classList[visible ? "remove" : "add"]("d-none");
        }

        function setError(message = "") {
            elements.error.textContent = message;
            show(elements.error, message !== "");
        }

        function reset(total, initialPayment = null) {
            openedTotal = total;
            elements.total.textContent = total;
            elements.method.value = "cash";
            elements.cashAmount.value = total;
            elements.mixedCash.value = "";
            elements.promptpayAmount.value = "0.00";
            elements.received.value = "";
            elements.change.textContent = "0.00";
            setError();
            updateVisibility();

            if (initialPayment) {
                restoreInitialPayment(initialPayment);
            }
        }

        function restoreInitialPayment(initialPayment) {
            try {
                const restored = resolve(
                    initialPayment.payment_method,
                    openedTotal,
                    initialPayment.cash_amount,
                    initialPayment.received_amount
                );
                const suppliedPromptpay = centsToMoney(moneyToCents(
                    initialPayment.promptpay_amount,
                    "à¸¢à¸­à¸”à¸žà¸£à¹‰à¸­à¸¡à¹€à¸žà¸¢à¹Œ",
                    false
                ));
                const suppliedCash = centsToMoney(moneyToCents(
                    initialPayment.cash_amount,
                    "à¹€à¸‡à¸´à¸™à¸ªà¸”à¸—à¸µà¹ˆà¹ƒà¸Šà¹‰à¸Šà¸³à¸£à¸°",
                    false
                ));

                if (restored.cash_amount !== suppliedCash
                    || restored.promptpay_amount !== suppliedPromptpay) {
                    throw new Error("payment allocation changed");
                }

                elements.method.value = restored.payment_method;
                updateVisibility();

                if (restored.payment_method === "mixed") {
                    elements.mixedCash.value = restored.cash_amount;
                }

                if (restored.payment_method !== "promptpay") {
                    elements.received.value = restored.received_amount;
                }

                updatePreview();
            } catch (ignored) {
                // A changed total or malformed historical payment must be re-entered.
            }
        }

        function updateVisibility() {
            const method = elements.method.value;

            show(elements.cashSummary, method === "cash");
            show(elements.mixedCashGroup, method === "mixed");
            show(elements.promptpayGroup, method !== "cash");
            show(elements.receivedGroup, method !== "promptpay");

            if (method === "cash") {
                elements.cashAmount.value = openedTotal;
                elements.mixedCash.value = "";
                elements.promptpayAmount.value = "0.00";
                elements.received.value = "";
            } else if (method === "promptpay") {
                elements.mixedCash.value = "";
                elements.promptpayAmount.value = openedTotal;
                elements.received.value = "0.00";
            } else {
                elements.mixedCash.value = "";
                elements.promptpayAmount.value = "0.00";
                elements.received.value = "";
            }

            elements.change.textContent = "0.00";
            setError();
        }

        function updatePreview() {
            try {
                const payment = resolve(
                    elements.method.value,
                    openedTotal,
                    elements.mixedCash.value,
                    elements.received.value
                );

                elements.promptpayAmount.value = payment.promptpay_amount;
                elements.change.textContent = payment.change_amount;
                setError();
            } catch (error) {
                elements.change.textContent = "0.00";

                if (elements.method.value === "mixed") {
                    try {
                        const total = moneyToCents(openedTotal, "ยอดสุทธิ", false);
                        const cash = moneyToCents(
                            elements.mixedCash.value,
                            "เงินสดที่ใช้ชำระ",
                            false
                        );

                        elements.promptpayAmount.value = cash > 0n && cash < total
                            ? centsToMoney(total - cash)
                            : "0.00";
                    } catch (ignored) {
                        elements.promptpayAmount.value = "0.00";
                    }
                }
            }
        }

        async function confirm() {
            if (confirming) {
                return;
            }

            const currentTotal = canonicalTotal();

            if (currentTotal !== openedTotal) {
                reset(currentTotal);
                setError("ยอดสุทธิมีการเปลี่ยนแปลง กรุณาระบุข้อมูลการชำระเงินใหม่");

                return;
            }

            let payment;

            try {
                payment = resolve(
                    elements.method.value,
                    openedTotal,
                    elements.mixedCash.value,
                    elements.received.value
                );
            } catch (error) {
                setError(error.message);

                return;
            }

            confirming = true;
            elements.confirm.disabled = true;
            setError();

            try {
                await options.onConfirm(payment);
                $(elements.modal).modal("hide");
            } catch (error) {
                setError(error.message || "ไม่สามารถบันทึกการขายได้");
            } finally {
                confirming = false;
                elements.confirm.disabled = false;
            }
        }

        function open() {
            if (confirming) {
                return;
            }

            reset(
                canonicalTotal(),
                typeof options.getInitialPayment === "function"
                    ? options.getInitialPayment()
                    : null
            );
            $(elements.modal).modal("show");
        }

        elements.method.addEventListener("change", updateVisibility);
        elements.mixedCash.addEventListener("input", updatePreview);
        elements.received.addEventListener("input", updatePreview);
        elements.confirm.addEventListener("click", confirm);
        elements.modal.addEventListener("keydown", function (event) {
            if (event.key === "Enter") {
                event.preventDefault();
                confirm();
            }
        });

        return Object.freeze({ open });
    }

    global.PosPayment = Object.freeze({
        createController,
        payload,
        resolve
    });
})(window);
