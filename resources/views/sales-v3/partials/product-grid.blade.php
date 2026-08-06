<div id="v3-product-grid" class="pos-v3-grid">
    @foreach ($products as $product)
        @php
            $productPlaceholder = asset('images/product-placeholder.svg');
            $stockState = $product->stock_qty <= 0
                ? 'is-out'
                : ($product->stock_qty <= $product->minimum_stock ? 'is-low' : 'is-in-stock');
        @endphp
        @php($productData = ['id' => $product->id, 'name' => $product->name, 'code' => $product->product_code, 'sku' => $product->sku, 'barcode' => $product->barcode, 'price' => $product->selling_price, 'cost_price' => $product->cost_price, 'rounding_direction' => $product->rounding_direction, 'rounding_unit' => $product->rounding_unit, 'stock_qty' => $product->stock_qty, 'minimum_stock' => $product->minimum_stock, 'unit' => $product->unit, 'category_id' => $product->category_id, 'category_rounding_override' => $product->category?->rounding_override, 'image_path' => $product->image_path, 'image_url' => $product->image_url, 'productUnits' => $product->productUnits])
        <button type="button" class="v3-product-card {{ $stockState }}" data-product='{{ json_encode($productData, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) }}' data-name="{{ strtolower($product->name) }}" data-category="{{ $product->category_id }}" data-search="{{ strtolower($product->name.' '.$product->product_code.' '.$product->sku.' '.$product->barcode) }}">
            <span class="v3-product-stock">เหลือ {{ number_format((float) $product->stock_qty, 2) }} {{ $product->unit ?: 'หน่วย' }}</span>
            <span class="v3-product-image">
                <img
                    src="{{ $product->image_url ?: $productPlaceholder }}"
                    alt=""
                    loading="lazy"
                    onerror="this.onerror=null;this.src='{{ $productPlaceholder }}';"
                >
            </span>
            <span class="v3-product-name" title="{{ $product->name }}">{{ $product->name }}</span>
            <strong class="v3-product-price">{{ number_format($product->selling_price, 2) }}</strong>
        </button>
    @endforeach
</div>
