<form method="GET" class="mb-3">

    <div class="row">

        <div class="col-md-5">
            <input type="text" name="search" class="form-control" placeholder="ค้นหาสินค้า..."
                value="{{ request('search') }}">
        </div>

        <div class="col-md-4">
            <select name="category" class="form-control">

                <option value="">
                    ทุกหมวดสินค้า
                </option>

                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(request('category') == $category->id)>

                        {{ $category->name }}

                    </option>
                @endforeach

            </select>
        </div>

        <div class="col-md-3">

            <button class="btn btn-primary">
                ค้นหา
            </button>

            <a href="{{ route('product-price-tiers.index') }}" class="btn btn-secondary">

                รีเซ็ต

            </a>

        </div>

    </div>

</form>

<div class="mb-3">

    <button
        type="button"
        class="btn btn-success"
        id="openBulkCopyTierModal">

        <i class="fas fa-copy"></i>
        Bulk Copy Tier

    </button>

</div>
