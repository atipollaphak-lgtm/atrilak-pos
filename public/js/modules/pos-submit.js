function bindSubmitSale() {

    const button = document.getElementById("btn-submit-sale");

    if (!button) {
        return;
    }

    button.addEventListener("click", function () {

        const items = cart.map(item => ({
            product_id: item.productId,
            product_unit_id: item.productUnitId,
            qty: item.qty,
            selling_price: item.price
        }));

        fetch("/sales-v2/store", {

            method: "POST",

            headers: {
                "Content-Type": "application/json",

                "X-CSRF-TOKEN": document
                    .querySelector('meta[name="csrf-token"]')
                    .getAttribute("content")
            },

            body: JSON.stringify({
                customer_id: document.getElementById("customer-id")?.value || null,

                customer_delivery_address_id:
                    document.getElementById("delivery-address-id")?.value || null,

                delivery_zone_id:
                    customerAddresses.find(function (item) {
                        return item.id == document.getElementById("delivery-address-id")?.value;
                    })?.delivery_zone_id || null,

                technician_id:
                    document.getElementById("technician-id")?.value || null,

                sale_date:
                    document.getElementById("sale-date")?.value,

                delivery_fee: deliveryFee,

                delivery_type:
                    document.getElementById("is-pickup")?.checked
                        ? "pickup"
                        : "delivery",

                discount: discount,

                items: items
            })

        })
            .then(async response => {

                const text = await response.text();

                let data = {};

                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error("Response is not JSON:", text);
                    throw new Error("Backend ไม่ได้ส่ง JSON กลับมา");
                }

                if (!response.ok) {
                    throw new Error(data.message || "ไม่สามารถบันทึกการขายได้");
                }

                return data;
            })
            .then(data => {

                console.log("POS V2 Response :", data);

                if (data.success && data.invoice_url) {
                    window.open(data.invoice_url, "_blank");

                    resetPOS();

                    return;
                }

                alert(data.message || "ไม่สามารถบันทึกการขายได้");

            })
            .catch(error => {

                console.error(error);

                alert(error.message || "เกิดข้อผิดพลาดระหว่างบันทึกการขาย");

            });

    });

}

function resetPOS() {

    // ล้างตะกร้า
    cart = [];
    renderCart();

    // ลูกค้าทั่วไป
    const customer = document.getElementById("customer-id");
    if (customer) {
        customer.value = "";
    }

    // ไม่ระบุช่าง
    const technician = document.getElementById("technician-id");
    if (technician) {
        technician.value = "";
    }

    // วันที่วันนี้
    const saleDate = document.getElementById("sale-date");
    if (saleDate) {
        saleDate.value = new Date().toISOString().slice(0, 10);
    }

    // เลขบิล
    // ยกเลิกติ๊กรับเอง
    const pickup = document.getElementById("is-pickup");

    if (pickup) {
        pickup.checked = false;
        pickup.dispatchEvent(new Event("change"));
    }

    // ล้างช่องค้นหาและโฟกัสกลับ
    const search = document.getElementById("pos-search-input");
    if (search) {
        search.value = "";
        search.focus();
    }
}
