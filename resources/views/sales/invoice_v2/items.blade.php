<table class="items-table">
    <thead>
        <tr>
            <th style="width:5%;">#</th>

            <th style="width:54%; text-align:left;">
                รายการสินค้า
            </th>

            <th style="width:10%;">
                จำนวน
            </th>

            <th style="width:6%;">
                หน่วย
            </th>

            <th style="width:12.5%;">
                หน่วยละ
            </th>

            <th style="width:12.5%;">
                ราคารวม
            </th>
        </tr>
    </thead>

    <tbody>
        @foreach ($sale->items as $index => $item)
            <tr>
                <td class="text-center">
                    {{ $index + 1 }}
                </td>

                <td class="item-name">
                    {{ $item->product_name_snapshot !== null
                        ? $item->product_name_snapshot
                        : ($item->product->name ?? '-') }}
                </td>

                <td class="qty">
                    {{ $formatNumber($item->qty) }}
                </td>

                <td class="unit">
                    {{ $item->unit_name_snapshot !== null
                        ? $item->unit_name_snapshot
                        : ($item->productUnit?->unit?->name
                            ?? $item->product?->unitRelation?->name
                            ?? $item->product?->unit
                            ?? '-') }}
                </td>

                <td class="price">
                    {{ $formatNumber($item->selling_price) }}
                </td>

                <td class="total">
                    {{ $formatNumber($item->total) }}
                </td>
            </tr>
                @endforeach

        @php
            $emptyRows = max(0, 15 - $sale->items->count());
        @endphp

        @for ($row = 0; $row < $emptyRows; $row++)
            <tr>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
            </tr>
        @endfor
    </tbody>
</table>
