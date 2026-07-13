document.addEventListener("DOMContentLoaded", function () {
    console.log("POS V2 JS loaded");

    initializePOS();
    bindKeyboardShortcut();
});

function initializePOS() {
    bindProductCards();
    bindBarcode();
    bindSearch();
    bindSubmitSale();

    bindCustomerDeliveryAddress();
    bindPickup();
}

function bindProductCards() {
    const cards = document.querySelectorAll(".product-card");

    console.log("Product Cards :", cards.length);

    cards.forEach(function (card) {
        card.addEventListener("click", function () {
            console.log("Clicked Product");

            const product = {
                id: card.dataset.id,
                barcode: card.dataset.barcode,
                name: card.dataset.name,
                price: parseFloat(card.dataset.price),
                stock_qty: Number(card.dataset.stockQty || card.dataset.stock || 0),
                unit: card.dataset.unit || "",

                productUnits: JSON.parse(
                    card.dataset.productUnits || "[]"
                ),
            };

            openQuantityDialog(product);
        });
    });
}

let currentQuantityProduct = null;

function openQuantityDialog(product) {

    currentQuantityProduct = product;

    const item = cart.find(function (cartItem) {

        return cartItem.productId == product.id;

    });

    document.getElementById("quantityProductName").textContent =
        product.name;

    document.getElementById("quantityStock").textContent =
        Number(product.stock_qty || product.stockQty || 0).toLocaleString()
        + " "
        + (product.unit || "");

    const input = document.getElementById("quantityInput");

    if (item) {
        input.value = item.qty;
    } else {
        input.value = "";
    }

    document.getElementById("quantityError").style.display = "none";

    $("#quantityModal")
        .off("shown.bs.modal")
        .on("shown.bs.modal", function () {

            input.focus();

            setTimeout(function () {
                input.select();
            }, 50);

        })
        .modal("show");

}

function confirmQuantityDialog() {

    if (!currentQuantityProduct) {
        return;
    }

    const input = document.getElementById("quantityInput");
    const error = document.getElementById("quantityError");

    const value = input.value.trim();

    // ช่องว่าง = ลบสินค้า
    if (value === "") {

        setCartQuantity(currentQuantityProduct, 0);

        $("#quantityModal").modal("hide");

        return;
    }

    const qty = Number(value);

    if (isNaN(qty) || qty < 0) {

        error.textContent = "❌ จำนวนไม่ถูกต้อง";
        error.style.display = "block";

        input.focus();
        input.select();

        return;
    }

    const stock = Number(
        currentQuantityProduct.stock_qty ||
        currentQuantityProduct.stockQty ||
        0
    );

    if (qty > stock) {

        error.textContent =
            "❌ สินค้าในสต๊อกมีเพียง "
            + stock.toLocaleString()
            + " "
            + (currentQuantityProduct.unit || "");

        error.style.display = "block";

        input.focus();
        input.select();

        return;
    }

    setCartQuantity(currentQuantityProduct, qty);

    $("#quantityModal").modal("hide");

}

function bindKeyboardShortcut() {

    document.addEventListener("keydown", function (e) {

        // F2 = Focus Barcode
        if (e.key === "F2") {

            e.preventDefault();

            const barcode = document.getElementById("pos-search-input");

            if (barcode) {

                barcode.focus();
                barcode.select();

            }

        }

        // F4 = Save Sale
        if (e.key === "F4") {

            e.preventDefault();

            const button = document.getElementById("btn-submit-sale");

            if (button) {
                button.click();
            }

        }

        // ESC = Clear Cart
        // ESC = Clear Cart
        if (e.key === "Escape") {

            const modal = document.getElementById("quantityModal");

            if (modal && modal.classList.contains("show")) {
                return;
            }

            e.preventDefault();

            if (cart.length === 0) {
                return;
            }

            if (!confirm("ต้องการล้างรายการทั้งหมดใช่หรือไม่?")) {
                return;
            }

            cart = [];

            renderCart();

            const search = document.getElementById("pos-search-input");

            if (search) {
                search.focus();
            }

        }

    });

    document.addEventListener("keydown", function (e) {

        const modal = document.getElementById("quantityModal");

        if (!modal || !modal.classList.contains("show")) {
            return;
        }

        // ทำงานเฉพาะตอนพิมพ์ในช่องจำนวน
        if (document.activeElement !== document.getElementById("quantityInput")) {
            return;
        }

        if (e.key === "Enter") {

            e.preventDefault();

            confirmQuantityDialog();
        }

        if (e.key === "Escape") {

            e.preventDefault();

            $("#quantityModal").modal("hide");
        }

    });

}

let customerAddresses = [];
let lastDeliveryFee = 0;

function bindCustomerDeliveryAddress() {

    const customerSelect =
        document.getElementById("customer-id");

    const addressSelect =
        document.getElementById("delivery-address-id");

    if (!customerSelect || !addressSelect) {
        return;
    }

    customerSelect.addEventListener("change", function () {

        const customerId = this.value;

        addressSelect.innerHTML =
            '<option>กำลังโหลด...</option>';

        if (!customerId) {

            addressSelect.innerHTML =
                '<option value="">เลือกลูกค้าก่อน</option>';

            return;
        }

        fetch(
            '/sales-v2/customers/' +
            customerId +
            '/delivery-addresses-json'
        )
            .then(function (response) {

                if (!response.ok) {
                    throw new Error('HTTP Error ' + response.status);
                }

                return response.json();

            })
            .then(addresses => {

                customerAddresses = addresses;

                addressSelect.innerHTML = '';

                if (addresses.length === 0) {

                    addressSelect.innerHTML =
                        '<option value="">ไม่มีที่อยู่จัดส่ง</option>';

                    return;
                }

                addresses.forEach(function (address) {

                    const option =
                        document.createElement("option");

                    option.value = address.id;

                    let text = '';

                    if (address.name) {
                        text += '🏠 ' + address.name;
                    } else {
                        text += '🏠 ที่อยู่ #' + address.id;
                    }

                    if (
                        address.delivery_zone &&
                        address.delivery_zone.name
                    ) {
                        text += ' (' + address.delivery_zone.name + ')';
                    }

                    option.text = text;

                    addressSelect.appendChild(option);

                });

                addressSelect.dispatchEvent(new Event('change'));

            });

    });

    addressSelect.addEventListener('change', function () {

        if (document.getElementById("is-pickup")?.checked) {

            deliveryFee = 0;

            document.getElementById("delivery-zone").textContent = "รับเอง";
            document.getElementById("delivery-fee").textContent = "0 บาท";

            renderCart();

            return;
        }

        const address = customerAddresses.find(function (item) {
            return item.id == addressSelect.value;
        });

        if (!address) {

            document.getElementById('delivery-receiver').textContent = '-';
            document.getElementById('delivery-phone').textContent = '-';
            document.getElementById('delivery-zone').textContent = '-';
            document.getElementById('delivery-fee').textContent = '0 บาท';

            deliveryFee = 0;
            renderCart();

            return;
        }

        document.getElementById('delivery-receiver').textContent =
            address.receiver_name || '-';

        document.getElementById('delivery-phone').textContent =
            address.receiver_phone || '-';

        document.getElementById('delivery-zone').textContent =
            address.delivery_zone
                ? address.delivery_zone.name
                : '-';

        document.getElementById('delivery-fee').textContent =
            (
                address.delivery_zone?.base_delivery_fee || 0
            ).toLocaleString()
            + ' บาท';

        deliveryFee = Number(
            address.delivery_zone?.base_delivery_fee || 0
        );

        renderCart();

    });

}

function bindPickup() {

    const pickupCheckbox =
        document.getElementById("is-pickup");

    const addressSelect =
        document.getElementById("delivery-address-id");

    if (!pickupCheckbox || !addressSelect) {
        return;
    }

    pickupCheckbox.addEventListener("change", function () {

        if (this.checked) {

            lastDeliveryFee = deliveryFee;

            deliveryFee = 0;

            addressSelect.disabled = true;

            document.getElementById("delivery-zone").textContent =
                "รับเอง";

            document.getElementById("delivery-fee").textContent =
                "0 บาท";

        } else {

            addressSelect.disabled = false;

            deliveryFee = lastDeliveryFee;

            addressSelect.dispatchEvent(new Event("change"));

        }

        renderCart();

    });

}
