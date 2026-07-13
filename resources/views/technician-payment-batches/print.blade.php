<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ใบรับเงินค่าช่าง {{ $batch->batch_no }}</title>

    <style>
        body {
            font-family: sans-serif;
            font-size: 14px;
            color: #000;
        }

        .container {
            width: 900px;
            margin: auto;
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }

        h2, h3, p {
            margin: 4px 0;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .doc-title {
            font-size: 24px;
            font-weight: bold;
            margin-top: 15px;
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
            padding: 10px 0;
        }

        .info-table,
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }

        .info-table td {
            border: none;
            padding: 5px;
        }

        .data-table th,
        .data-table td {
            border: 1px solid #000;
            padding: 7px;
        }

        .technician-title {
            margin-top: 25px;
            font-size: 16px;
            font-weight: bold;
        }

        .grand-total {
            margin-top: 20px;
            text-align: right;
            font-size: 20px;
            font-weight: bold;
        }

        .remark {
            margin-top: 20px;
        }

        .signature {
            margin-top: 70px;
            display: flex;
            justify-content: space-between;
        }

        .signature div {
            width: 45%;
            text-align: center;
        }

        .no-print {
            text-align: right;
            margin-bottom: 15px;
        }

        @media print {
            .no-print {
                display: none;
            }

            body {
                margin: 0;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <div class="no-print">
        <button onclick="window.print()">พิมพ์</button>
    </div>

    <div class="header">
        <h2>อตรีลักษณ์ คอนกรีต</h2>
        <p>ระบบ ATRILAK POS</p>

        <div class="doc-title">
            ใบรับเงินค่าช่าง
        </div>
    </div>

    <table class="info-table">
        <tr>
            <td>
                <strong>เลขที่รอบจ่าย:</strong>
                {{ $batch->batch_no }}
            </td>
            <td class="text-right">
                <strong>วันที่จ่าย:</strong>
                {{ $batch->payment_date }}
            </td>
        </tr>
        <tr>
            <td>
                <strong>จำนวนช่าง:</strong>
                {{ $batch->total_technicians }} คน
            </td>
            <td class="text-right">
                <strong>จำนวนรายการ:</strong>
                {{ $batch->total_items }} รายการ
            </td>
        </tr>
    </table>

    @foreach ($groups as $technicianId => $commissions)
        @php
            $technician = $commissions->first()->technician;
            $total = $commissions->sum('commission_amount');
        @endphp

        <div class="technician-title">
            ช่าง: {{ $technician->name ?? '-' }}
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 45px;">#</th>
                    <th>เลขบิล</th>
                    <th class="text-right">ยอดขาย</th>
                    <th class="text-right">ค่าช่าง</th>
                </tr>
            </thead>

            <tbody>
                @foreach ($commissions as $commission)
                    <tr>
                        <td class="text-center">{{ $loop->iteration }}</td>
                        <td>{{ $commission->sale->sale_no ?? '-' }}</td>
                        <td class="text-right">
                            {{ number_format($commission->sale_total ?? 0, 2) }}
                        </td>
                        <td class="text-right">
                            {{ number_format($commission->commission_amount ?? 0, 2) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>

            <tfoot>
                <tr>
                    <th colspan="3" class="text-right">รวมค่าช่าง</th>
                    <th class="text-right">
                        {{ number_format($total, 2) }}
                    </th>
                </tr>
            </tfoot>
        </table>
    @endforeach

    <div class="grand-total">
        รวมทั้งสิ้น {{ number_format($batch->total_amount, 2) }} บาท
    </div>

    @if (!empty($batch->remark))
        <div class="remark">
            <strong>หมายเหตุ:</strong>
            {{ $batch->remark }}
        </div>
    @endif

    <div class="signature">
        <div>
            ........................................<br>
            ผู้รับเงิน / ช่าง<br>
            วันที่ ........../........../..........
        </div>

        <div>
            ........................................<br>
            ผู้จ่ายเงิน<br>
            วันที่ ........../........../..........
        </div>
    </div>

</div>

<script>
    window.onload = function () {
        window.print();
    }
</script>

</body>
</html>
