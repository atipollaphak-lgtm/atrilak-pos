<div class="pos-v3-nav">
    <input id="v3-product-search" class="form-control form-control-lg" autocomplete="off" placeholder="ค้นหาชื่อสินค้า รหัสสินค้า หรือ Barcode">
    <div class="d-flex flex-wrap align-items-center mt-2">
        <button type="button" class="v3-filter active" data-filter="all">ทั้งหมด</button>
        <button type="button" class="v3-filter" data-filter="best">ขายดี</button>
        <button type="button" class="v3-filter" data-filter="favorite">รายการโปรด</button>
        <label class="ml-auto small mb-0"><input id="v3-stock-only" type="checkbox"> เฉพาะมีสต็อก</label>
    </div>
    <div id="v3-category-tabs" class="pos-v3-tabs mt-2">
        <button type="button" class="v3-category active" data-category="">ทุกหมวด</button>
        @foreach ($categories as $category)<button type="button" class="v3-category" data-category="{{ $category->id }}">{{ $category->name }}</button>@endforeach
    </div>
</div>
