<div class="pos-v3-nav">
    <input id="v3-stock-only" type="checkbox" class="d-none">
    <div id="v3-category-tabs" class="pos-v3-tabs">
        <button type="button" class="v3-category active" data-category="frequent">ขายบ่อย</button>
        <button type="button" class="v3-category" data-category="">ทุกหมวด</button>
        @foreach ($categories as $category)<button type="button" class="v3-category" data-category="{{ $category->id }}">{{ $category->name }}</button>@endforeach
    </div>
    @if (in_array(auth()->user()?->role, ['manager', 'owner'], true))
        <div class="pos-v3-product-order-controls" aria-live="polite">
            <button id="v3-product-order-toggle" type="button" class="btn btn-outline-primary">จัดลำดับสินค้า</button>
            <span id="v3-product-order-status" class="pos-v3-product-order-status" role="status">เลือกหมวดหมู่สินค้าเพื่อจัดลำดับ</span>
            <button id="v3-product-order-save" type="button" class="btn btn-primary d-none" disabled>บันทึกลำดับ</button>
            <button id="v3-product-order-cancel" type="button" class="btn btn-outline-secondary d-none">ยกเลิก</button>
        </div>
    @endif
</div>
