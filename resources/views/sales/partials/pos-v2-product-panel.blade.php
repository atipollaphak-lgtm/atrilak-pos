<div class="col-lg-8">

    <div class="card pos-product-panel">

        <div class="card-header d-flex align-items-center justify-content-between">

            <div>
                <i class="fas fa-box-open mr-2"></i>
                เลือกสินค้า
            </div>

            <small class="font-weight-normal">
                คลิกสินค้า หรือยิงบาร์โค้ด
            </small>

        </div>

        <div class="card-body">

            <div class="pos-search-wrapper">

                <i class="fas fa-search pos-search-icon"></i>

                <input
                    id="pos-search-input"
                    class="form-control form-control-lg"
                    placeholder="ยิงบาร์โค้ด หรือพิมพ์ชื่อสินค้า..."
                    autocomplete="off"
                >

            </div>

            <div class="pos-search-help">
    <i class="fas fa-keyboard mr-1"></i>
    พิมพ์ชื่อสินค้าแล้วเลือกจากรายการด้านล่าง
</div>

<div class="pos-category-bar mb-3">

    <button
        type="button"
        class="btn btn-sm pos-category-btn active"
        data-category="">
        ทั้งหมด
    </button>

    <button
        type="button"
        class="btn btn-sm pos-category-btn"
        data-category="ปูน">
        ปูน
    </button>

    <button
        type="button"
        class="btn btn-sm pos-category-btn"
        data-category="อิฐ">
        อิฐ
    </button>

    <button
        type="button"
        class="btn btn-sm pos-category-btn"
        data-category="เหล็ก">
        เหล็ก
    </button>

    <button
        type="button"
        class="btn btn-sm pos-category-btn"
        data-category="เสา">
        เสา
    </button>

    <button
        type="button"
        class="btn btn-sm pos-category-btn"
        data-category="หินทราย">
        หินทราย
    </button>

</div>

<div class="row pos-product-grid">

                @foreach ($products as $product)

                    <div class="col-xl-3 col-lg-4 col-md-6 mb-3">

                        <div
    class="card shadow-sm h-100 product-card"
    data-id="{{ $product->id }}"
    data-barcode="{{ $product->barcode }}"
    data-name="{{ $product->name }}"
    data-price="{{ $product->selling_price }}"
    data-stock="{{ $product->stock_qty }}"
    data-category-id="{{ $product->category_id }}"
    data-category-name="{{ strtolower($product->category->name ?? '') }}"
    data-product-units='@json($product->productUnits)'
>

                            <div class="card-body">

                                <div class="product-card-top">

                                    <div class="product-name">
                                        {{ $product->name }}
                                    </div>

                                    <div class="product-stock-badge
                                        {{ $product->stock_qty <= 0
                                            ? 'is-out'
                                            : ($product->stock_qty <= $product->minimum_stock
                                                ? 'is-low'
                                                : '') }}">

                                        @if ($product->stock_qty <= 0)
                                            หมด
                                        @else
                                            คงเหลือ {{ number_format($product->stock_qty) }}
                                        @endif

                                    </div>

                                </div>

                                <div class="product-price">

                                    <span class="product-price-number">
                                        {{ number_format($product->selling_price, 2) }}
                                    </span>

                                    <span class="product-price-unit">
                                        บาท
                                    </span>

                                </div>

                                <div class="product-card-footer">

                                    <span>
                                        <i class="fas fa-hand-pointer mr-1"></i>
                                        คลิกเพื่อเลือก
                                    </span>

                                </div>

                            </div>

                        </div>

                    </div>

                @endforeach

            </div>

        </div>

    </div>

</div>
