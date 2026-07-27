let cart = [];

let deliveryFee = 0;
let discount = 0;

function getDefaultUnit(product) {

    if (!product.productUnits || product.productUnits.length === 0) {

        return {
            id: null,
            selling_price: product.price,
            is_sale_unit: true,
            unit: {
                name: product.unit || "หน่วย"
            },
            price_tiers: []
        };

    }

    if (product.forceProductUnitId) {

        const selected = product.productUnits.find(function (unit) {
            return Number(unit.id) === Number(product.forceProductUnitId);
        });

        if (selected) {
            return selected;
        }

    }

    let saleUnit = product.productUnits.find(function (unit) {
        return unit.is_sale_unit === true || unit.is_sale_unit === 1;
    });

    if (saleUnit) {
        return saleUnit;
    }

    return product.productUnits[0];
}

function getUnitPrice(unit, qty) {
    let price = Number(unit.selling_price || 0);

    if (!unit.price_tiers || unit.price_tiers.length === 0) {
        return price;
    }

    unit.price_tiers.forEach(function (tier) {
        if (qty >= Number(tier.min_qty)) {

            if (tier.fixed_price !== null && tier.fixed_price !== "") {
                price = Number(tier.fixed_price);
            } else if (tier.discount_percent !== null && tier.discount_percent !== "") {
                price = Number(unit.selling_price) * (1 - Number(tier.discount_percent) / 100);
            }

        }
    });

    return price;
}

function buildCartItem(product) {
    const unit = getDefaultUnit(product);

    if (!unit) {
        alert("สินค้านี้ยังไม่มีหน่วยขาย");
        return null;
    }

    const qty = 1;
    const price = getUnitPrice(unit, qty);

    let barcode = product.barcode;

    if (unit.barcodes && unit.barcodes.length > 0) {

        const defaultBarcode =
            unit.barcodes.find(function (b) {
                return b.is_default;
            }) || unit.barcodes[0];

        if (defaultBarcode) {
            barcode = defaultBarcode.barcode;
        }

    }

    return {
        id: product.id,
        productId: product.id,
        productUnitId: unit.id,

        name: product.name,
        unitName: unit.unit?.name || product.unit || "หน่วย",

        barcode: barcode,
        qty: qty,
        price: price,

        stockQty: Number(product.stock_qty || product.stockQty || 0),

        product: product,
        unit: unit,
    };
}

function addToCart(product) {
    const newItem = buildCartItem(product);

    if (!newItem) {
        return;
    }

    const index = cart.findIndex(function (item) {
        return item.productId == newItem.productId
            && item.productUnitId == newItem.productUnitId;
    });

    if (index >= 0) {
        cart[index].qty++;

        cart[index].price = getUnitPrice(
            cart[index].unit,
            cart[index].qty
        );
    } else {
        cart.push(newItem);
    }

    renderCart();
}

function setCartQuantity(product, qty) {

    const newItem = buildCartItem(product);

    if (!newItem) {
        return;
    }

    const index = cart.findIndex(function (item) {
        return item.productId == newItem.productId
            && item.productUnitId == newItem.productUnitId;
    });

    if (qty <= 0) {

        if (index >= 0) {
            cart.splice(index, 1);
        }

        renderCart();
        return;
    }

    newItem.qty = qty;
    newItem.price = getUnitPrice(newItem.unit, qty);

    if (index >= 0) {

        cart[index] = newItem;

    } else {

        cart.push(newItem);

    }

    renderCart();
}

function findCartIndex(productId, productUnitId) {
    return cart.findIndex(function (item) {
        return String(item.productId) === String(productId)
            && String(item.productUnitId ?? "") === String(productUnitId ?? "");
    });
}

function renderCart() {
    const cartItems = document.getElementById("cart-items");
    const cartSubtotal = document.getElementById("cart-subtotal");
    const cartTotal = document.getElementById("cart-total");

    if (!cartItems || !cartSubtotal || !cartTotal) {
        console.error("Cart elements not found");
        return;
    }

    cartItems.innerHTML = "";

    if (cart.length === 0) {

    cartItems.innerHTML = `
        <tr>
            <td colspan="5" class="pos-empty-cart">

                <div class="pos-empty-cart-icon">
                    <i class="fas fa-shopping-basket"></i>
                </div>

                <div class="pos-empty-cart-title">
                    ยังไม่มีสินค้า
                </div>

                <div class="pos-empty-cart-text">
                    คลิกสินค้า หรือยิงบาร์โค้ดเพื่อเริ่มขาย
                </div>

            </td>
        </tr>
    `;

    updateCartSummary(0);

    return;
}

    let subtotal = 0;

    cart.forEach(function (item) {
        const lineTotal = item.qty * item.price;

        subtotal += lineTotal;

        cartItems.innerHTML += `
            <tr
                class="cart-row"
                data-product-id="${item.productId}"
                data-product-unit-id="${item.productUnitId ?? ""}"
            >
                <td>
    <strong>${item.name}</strong>

    <br>

    <small class="text-muted">
        ${item.unitName}
    </small>
</td>

                <td class="text-center">
                    <button
                        class="btn btn-sm btn-outline-secondary cart-minus"
                        data-product-id="${item.productId}"
                        data-product-unit-id="${item.productUnitId ?? ""}"
                    >
                        −
                    </button>

                    <strong
                        style="
                            display:inline-block;
                            width:32px;
                            text-align:center;
                        "
                    >
                        ${item.qty}
                    </strong>

                    <button
                        class="btn btn-sm btn-outline-primary cart-plus"
                        data-product-id="${item.productId}"
                        data-product-unit-id="${item.productUnitId ?? ""}"
                    >
                        +
                    </button>
                </td>

                <td class="text-right">
                    ${formatMoney(item.price)}
                </td>

                <td class="text-right">
    ${formatMoney(lineTotal)}
</td>

<td class="text-center">

    <button
        class="btn btn-sm btn-outline-danger cart-remove"
        data-product-id="${item.productId}"
        data-product-unit-id="${item.productUnitId ?? ""}"
        title="ลบรายการ"
    >
        🗑️
    </button>

</td>
            </tr>
        `;
    });

    updateCartSummary(subtotal);

    bindCartButtons();
bindCartRows();
bindRemoveButtons();
}

function updateCartSummary(subtotal) {

    const fee = Number(deliveryFee || 0);
    const dis = Number(discount || 0);

    const subtotalElement =
        document.getElementById("cart-subtotal");

    const feeElement =
        document.getElementById("delivery-fee-total");

    const discountElement =
        document.getElementById("discount-total");

    const totalElement =
        document.getElementById("cart-total");

    if (
        !subtotalElement ||
        !feeElement ||
        !discountElement ||
        !totalElement
    ) {
        return;
    }

    subtotalElement.textContent =
        formatMoney(subtotal);

    feeElement.textContent =
        formatMoney(fee);

    discountElement.textContent =
        formatMoney(dis);

    totalElement.textContent =
        formatMoney(
            subtotal + fee - dis
        );

}

function bindCartButtons() {
    document.querySelectorAll(".cart-plus").forEach(function (button) {
        button.addEventListener("click", function () {
            const index = findCartIndex(
                this.dataset.productId,
                this.dataset.productUnitId
            );
            const item = cart[index];

            if (item) {

                item.qty++;

                item.price = getUnitPrice(
                    item.unit,
                    item.qty
                );

                renderCart();
            }
        });
    });

    document.querySelectorAll(".cart-minus").forEach(function (button) {
        button.addEventListener("click", function () {
            const index = findCartIndex(
                this.dataset.productId,
                this.dataset.productUnitId
            );

            if (index === -1) {
                return;
            }

            cart[index].qty--;

            if (cart[index].qty > 0) {

                cart[index].price = getUnitPrice(
                    cart[index].unit,
                    cart[index].qty
                );

            } else {

                cart.splice(index, 1);

            }

            renderCart();
        });
    });
}


function bindCartRows() {

    document.querySelectorAll(".cart-row").forEach(function (row) {

        row.addEventListener("click", function (e) {

            // ถ้าคลิกปุ่ม + - ลบ ไม่ต้องเปิด Popup
            if (
                e.target.closest(".cart-plus") ||
                e.target.closest(".cart-minus") ||
                e.target.closest(".cart-remove")
            ) {
                return;
            }

            const productId = this.dataset.productId;
            const productUnitId = this.dataset.productUnitId;

            const item = cart[findCartIndex(productId, productUnitId)];

            if (!item) {
                return;
            }

            openQuantityDialog({
                ...item.product,
                forceProductUnitId: item.productUnitId,
            });

        });

    });

}

function bindRemoveButtons() {
    document.querySelectorAll(".cart-remove").forEach(function (button) {
        button.addEventListener("click", function () {
            const index = findCartIndex(
                this.dataset.productId,
                this.dataset.productUnitId
            );

            if (index === -1) {
                return;
            }

            cart.splice(index, 1);

            renderCart();
        });
    });
}
