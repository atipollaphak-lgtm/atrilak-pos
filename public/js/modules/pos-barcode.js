function bindBarcode() {

    const input = document.getElementById("pos-search-input");

    if (!input) {
        return;
    }

    input.addEventListener("keydown", function (event) {

        if (event.key !== "Enter") {
            return;
        }

        event.preventDefault();

        const barcode = input.value.trim();

        if (barcode === "") {
            return;
        }

        const product = findProductByBarcode(barcode);

        if (!product) {

            alert("ไม่พบสินค้าบาร์โค้ดนี้");

            input.select();

            return;

        }

        addToCart(product);

        input.value = "";

        input.focus();

    });

}


function findProductByBarcode(barcode) {

    const cards = document.querySelectorAll(".product-card");

    for (const card of cards) {

        const productUnits = JSON.parse(
            card.dataset.productUnits || "[]"
        );

        for (const unit of productUnits) {

            if (!unit.barcodes) {
                continue;
            }

            for (const item of unit.barcodes) {

                if (item.barcode !== barcode) {
                    continue;
                }

                return {
                    id: card.dataset.id,
                    productId: card.dataset.id,

                    name: card.dataset.name,

                    barcode: item.barcode,

                    price: Number(unit.selling_price),

                    qty: 1,

                    productUnits: productUnits,

                    forceProductUnitId: unit.id,
                };
            }

        }

    }

    return null;
}
