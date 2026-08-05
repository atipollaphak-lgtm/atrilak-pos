<div class="pos-v3-nav">
    <input id="v3-stock-only" type="checkbox" class="d-none">
    <div class="pos-v3-price-zone-control">
        <label for="v3-price-zone-select" class="sr-only">โซนราคาจัดส่ง</label>
        <span class="small text-muted mr-2">โซนราคาจัดส่ง</span>
        <select id="v3-price-zone-select" class="form-control form-control-sm" aria-label="โซนราคาจัดส่ง">
            <option value="">เลือกเมื่อจัดส่งก่อนเลือกลูกค้า</option>
            @foreach ($deliveryZones as $zone)
                <option value="{{ $zone->id }}" data-zone='@json($zone)'>{{ $zone->name }}</option>
            @endforeach
        </select>
    </div>
    <div id="v3-category-tabs" class="pos-v3-tabs">
        <button type="button" class="v3-category active" data-category="">ทุกหมวด</button>
        @foreach ($categories as $category)<button type="button" class="v3-category" data-category="{{ $category->id }}">{{ $category->name }}</button>@endforeach
    </div>
</div>
