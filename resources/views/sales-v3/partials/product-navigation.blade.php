<div class="pos-v3-nav">
    <input id="v3-stock-only" type="checkbox" class="d-none">
    <div id="v3-category-tabs" class="pos-v3-tabs">
        <button type="button" class="v3-category active" data-category="">ทุกหมวด</button>
        @foreach ($categories as $category)<button type="button" class="v3-category" data-category="{{ $category->id }}">{{ $category->name }}</button>@endforeach
    </div>
</div>
