<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>ใบรับสินค้า #{{ $purchase->id }}</title>

    <style>

        body{
            font-family:sans-serif;
            font-size:14px;
        }

        table{
            width:100%;
            border-collapse:collapse;
            margin-top:15px;
        }

        th,td{
            border:1px solid #000;
            padding:8px;
        }

        .text-right{
            text-align:right;
        }

        @media print{
            .no-print{
                display:none;
            }
        }

    </style>

</head>

<body>

    <button
        onclick="window.print()"
        class="no-print">

        พิมพ์

    </button>

    <h2>
        ใบรับสินค้า
    </h2>

    <p>
        เลขที่ :
        {{ $purchase->id }}
    </p>

    <p>
        วันที่ :
        {{ $purchase->purchase_date }}
    </p>

    <p>
        ผู้จำหน่าย :
        {{ $purchase->supplier->name ?? '-' }}
    </p>

    <table>

        <thead>

            <tr>
                <th>สินค้า</th>
                <th>จำนวน</th>
                <th>ต้นทุน</th>
                <th>รวม</th>
            </tr>

        </thead>

        <tbody>

            @foreach($purchase->items as $item)

            <tr>

                <td>
                    {{ $item->product->name ?? '-' }}
                </td>

                <td>
                    {{ rtrim(rtrim(number_format($item->qty, 4, '.', ''), '0'), '.') }}
                </td>

                <td>
                    {{ number_format($item->cost_price,2) }}
                </td>

                <td>
                    {{ number_format($item->total,2) }}
                </td>

            </tr>

            @endforeach

        </tbody>

        <tfoot>

            <tr>

                <th colspan="3"
                    class="text-right">

                    รวมทั้งสิ้น

                </th>

                <th>
                    {{ number_format($purchase->total_amount,2) }}
                </th>

            </tr>

        </tfoot>

    </table>

</body>
</html>
