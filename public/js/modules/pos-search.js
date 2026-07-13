function bindSearch() {

    const input = document.getElementById("pos-search-input");

    if (!input) {
        return;
    }

    const categoryButtons =
        document.querySelectorAll(".pos-category-btn");

    let currentCategory = "";

    function filterProducts() {

        const keyword =
            input.value.trim().toLowerCase();

        const cards =
            document.querySelectorAll(".product-card");

        cards.forEach(function (card) {

            const name =
                (card.dataset.name || "").toLowerCase();

            const barcode =
                (card.dataset.barcode || "").toLowerCase();

            const category =
                (card.dataset.categoryName || "").toLowerCase();

            const matchKeyword =
                keyword === "" ||
                name.includes(keyword) ||
                barcode.includes(keyword);

            const matchCategory =
                currentCategory === "" ||
                category === currentCategory.toLowerCase();

            const column = card.parentElement;

            if (column) {
                column.style.display =
                    (matchKeyword && matchCategory)
                        ? ""
                        : "none";
            }

        });

    }

    input.addEventListener("input", filterProducts);

    categoryButtons.forEach(function (button) {

        button.addEventListener("click", function () {

            categoryButtons.forEach(function (item) {
                item.classList.remove("active");
            });

            this.classList.add("active");

            currentCategory =
                this.dataset.category || "";

            filterProducts();

        });

    });

}
