<div class="table-responsive">
    <table class="table table-bordered table-hover">
        <thead>
            <tr>
                <th style="width: 80px;">รหัส</th>
                <th>สินค้า</th>
                <th>หมวดหมู่</th>
                <th>หน่วยขาย</th>
                <th>Price Tier</th>
                <th style="width: 170px;">จัดการ</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($products as $product)
                <tr>
                    <td>{{ $product->id }}</td>
                    <td>
                        <strong>{{ $product->name }}</strong>
                    </td>
                    <td>
                        {{ $product->category->name ?? '-' }}
                    </td>
                    <td>
                        @forelse ($product->productUnits as $productUnit)
                            <div class="mb-2">
                                <span class="badge badge-secondary">
                                    {{ $productUnit->unit->name ?? ($productUnit->unit_name ?? '-') }}
                                </span>

                                <span class="text-muted">
                                    ราคา {{ number_format($productUnit->selling_price ?? 0, 2) }}
                                </span>
                            </div>
                        @empty
                            <span class="text-muted">ยังไม่มีหน่วยขาย</span>
                        @endforelse
                    </td>
                    <td>
                        @forelse ($product->productUnits as $productUnit)
                            <div class="mb-3">
                                <div class="mb-1">
                                    <strong>
                                        {{ $productUnit->unit->name ?? ($productUnit->unit_name ?? '-') }}
                                    </strong>
                                </div>

                                @forelse ($productUnit->priceTiers as $tier)
                                    <div class="border rounded p-2 mb-1">
                                        <span class="badge badge-primary">
                                            {{ number_format($tier->min_qty) }}+
                                        </span>

                                        @if (!is_null($tier->fixed_price))
                                            <span>
                                                ราคาคงที่ {{ number_format($tier->fixed_price, 2) }}
                                            </span>
                                        @else
                                            <span>
                                                ลด {{ number_format($tier->discount_percent ?? 0, 2) }}%
                                            </span>
                                        @endif

                                        @if ($tier->active)
                                            <span class="badge badge-success">เปิดใช้</span>
                                        @else
                                            <span class="badge badge-secondary">ปิด</span>
                                        @endif

                                        <div class="mt-2 d-flex">

    <button
        type="button"
        class="btn btn-sm btn-warning btn-edit-tier mr-1"
        data-tier-id="{{ $tier->id }}"
        data-product-unit-id="{{ $productUnit->id }}"
        data-min-qty="{{ $tier->min_qty }}"
        data-discount-percent="{{ $tier->discount_percent }}"
        data-fixed-price="{{ $tier->fixed_price }}"
        data-active="{{ $tier->active ? 1 : 0 }}">

        แก้ไข

    </button>

    <form
        action="{{ route('product-price-tiers.destroy', $tier) }}"
        method="POST"
        onsubmit="return confirm('ต้องการลบ Price Tier นี้ใช่หรือไม่?');">

        @csrf
        @method('DELETE')

        <button
            type="submit"
            class="btn btn-sm btn-danger">

            ลบ

        </button>

    </form>

</div>
                                    </div>
                                @empty
                                    <div class="text-muted">
                                        ยังไม่มี Tier
                                    </div>
                                @endforelse
                            </div>
                        @empty
                            <span class="text-muted">-</span>
                        @endforelse
                    </td>

                    <td>
                        @forelse ($product->productUnits as $productUnit)
                            <div class="mb-2">

                                <button type="button" class="btn btn-sm btn-success btn-add-tier"
                                    data-product-id="{{ $product->id }}"
                                    data-product-unit-id="{{ $productUnit->id }}"
                                    data-product-name="{{ $product->name }}"
                                    data-unit-name="{{ $productUnit->unit->name ?? ($productUnit->unit_name ?? '-') }}">

                                    + {{ $productUnit->unit->name ?? ($productUnit->unit_name ?? '-') }}

                                </button>

                            </div>

                        @empty

                            <span class="text-muted">-</span>
                        @endforelse
                    </td>

                </tr>
            @endforeach
        </tbody>
    </table>
</div>
