<div class="row">
    <div class="col-md-3 mb-2">
        <div class="text-muted small">ซื้อได้</div>
        <strong>{{ $productUnit->is_purchase_unit ? 'ใช่' : '-' }}</strong>
    </div>

    <div class="col-md-3 mb-2">
        <div class="text-muted small">ขายได้</div>
        <strong>{{ $productUnit->is_sale_unit ? 'ใช่' : '-' }}</strong>
    </div>

    <div class="col-md-3 mb-2">
        <div class="text-muted small">ราคาซื้อ</div>
        <strong>{{ number_format($productUnit->purchase_price ?? 0, 2) }}</strong>
    </div>

    <div class="col-md-3 mb-2">
        <div class="text-muted small">ราคาขาย</div>
        <strong>{{ number_format($productUnit->selling_price ?? 0, 2) }}</strong>
    </div>
</div>
