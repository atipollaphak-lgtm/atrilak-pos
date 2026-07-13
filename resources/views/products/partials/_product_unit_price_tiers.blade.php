<div class="mt-3 p-2 bg-light rounded">

    <div class="d-flex justify-content-between align-items-center mb-2">

        <div class="text-muted small font-weight-bold">
            💰 ราคาตามจำนวน
        </div>

        <button type="button" class="btn btn-sm btn-primary" data-toggle="modal"
            data-target="#createPriceTierModal{{ $productUnit->id }}">
            <i class="fas fa-plus"></i>
            เพิ่ม Price Tier
        </button>

    </div>

    @php
        $priceTiers = $productUnit->priceTiers->sortBy('min_qty');
    @endphp

    @if ($priceTiers->isEmpty())

        <div class="text-muted">
            ยังไม่มี Price Tier
        </div>
    @else
        <table class="table table-sm table-bordered">

            <thead>

                <tr>

                    <th width="120">
                        จำนวนขั้นต่ำ
                    </th>

                    <th width="120">
                        ลด %
                    </th>

                    <th width="140">
                        ราคาพิเศษ
                    </th>

                    <th width="100">
                        สถานะ
                    </th>

                    <th width="120">
                        จัดการ
                    </th>

                </tr>

            </thead>

            <tbody>

                @foreach ($priceTiers as $tier)
                    <tr>

                        <td>

                            {{ number_format($tier->min_qty) }}

                        </td>

                        <td>

                            {{ number_format($tier->discount_percent, 2) }}

                        </td>

                        <td>

                            {{ $tier->fixed_price ? number_format($tier->fixed_price, 2) : '-' }}

                        </td>

                        <td>

                            @if ($tier->active)
                                <span class="badge badge-success">
                                    ใช้งาน
                                </span>
                            @else
                                <span class="badge badge-secondary">
                                    ปิด
                                </span>
                            @endif

                        </td>

                        <td>

                            <button
    type="button"
    class="btn btn-warning btn-sm"
    data-toggle="modal"
    data-target="#editPriceTierModal{{ $tier->id }}">

    <i class="fas fa-edit"></i>

</button>

                            <form method="POST"
                                action="{{ route('products.price-tiers.destroy', [$product, $productUnit, $tier]) }}"
                                style="display:inline;" onsubmit="return confirm('ยืนยันลบ Price Tier นี้?');">

                                @csrf
                                @method('DELETE')

                                <button type="submit" class="btn btn-danger btn-sm">

                                    <i class="fas fa-trash"></i>

                                </button>

                            </form>

                        </td>

                    </tr>
                @endforeach

            </tbody>

        </table>

    @endif

</div>
