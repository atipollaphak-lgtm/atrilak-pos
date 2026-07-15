<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>ใบเสนอราคา {{ $quotation->quotation_no }}</title>

    <style>
        body {
            font-family: sans-serif;
            font-size: 14px;
            color: #000;
        }

        .container {
            width: 800px;
            margin: auto;
        }

        .no-print {
            margin-bottom: 15px;
        }

        .header {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }

        .logo {
            width: 90px;
            height: 90px;
            object-fit: contain;
            margin-right: 15px;
        }

        .store-info {
            flex: 1;
            line-height: 1.6;
        }

        .store-name {
            font-size: 22px;
            font-weight: bold;
        }

        .title {
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            margin: 15px 0;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 8px;
        }

        .no-border td {
            border: none;
            padding: 4px;
        }

        .total {
            font-size: 18px;
            font-weight: bold;
        }

        .signature {
            margin-top: 60px;
            display: flex;
            justify-content: space-between;
        }

        .signature-box {
            width: 45%;
            text-align: center;
        }

        .qr-box {
            text-align: center;
        }

        .qr {
            width: 120px;
            height: 120px;
            object-fit: contain;
        }

        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>

<body>

    @php
        $setting = \App\Models\Setting::first();

        $logoBase64 = null;
        $qrBase64 = null;

        if ($setting && $setting->logo_image) {
            $logoPath = storage_path('app/public/' . $setting->logo_image);

            if (file_exists($logoPath)) {
                $logoType = pathinfo($logoPath, PATHINFO_EXTENSION);
                $logoData = base64_encode(file_get_contents($logoPath));
                $logoBase64 = 'data:image/' . $logoType . ';base64,' . $logoData;
            }
        }

        if ($setting && $setting->qr_image) {
            $qrPath = storage_path('app/public/' . $setting->qr_image);

            if (file_exists($qrPath)) {
                $qrType = pathinfo($qrPath, PATHINFO_EXTENSION);

                $qrData = base64_encode(file_get_contents($qrPath));

                $qrBase64 = 'data:image/' . $qrType . ';base64,' . $qrData;
            }
        }

        $storeName = \App\Support\DocumentSnapshotValue::resolve(
            $quotation->store_name_snapshot,
            $setting?->store_name,
            'ATRILAK POS'
        );
        $storeAddress = \App\Support\DocumentSnapshotValue::resolve(
            $quotation->store_address_snapshot,
            $setting?->store_address,
            ''
        );
        $storePhone = \App\Support\DocumentSnapshotValue::resolve(
            $quotation->store_phone_snapshot,
            $setting?->store_phone
        );
        $storeTaxNumber = \App\Support\DocumentSnapshotValue::resolve(
            $quotation->store_tax_number_snapshot,
            $setting?->tax_number
        );
    @endphp

    <div class="container">

        <div class="no-print">
            <button onclick="window.print()">พิมพ์</button>
        </div>

        <div class="header">

            @if ($logoBase64)
                <img src="{{ $logoBase64 }}" class="logo">
            @endif

            <div class="store-info">
                <div class="store-name">
                    {{ $storeName }}
                </div>

                <div>
                    {{ $storeAddress }}
                </div>

                <div>
                    โทร: {{ $storePhone }}
                </div>

                <div>
                    เลขประจำตัวผู้เสียภาษี:
                    {{ $storeTaxNumber }}
                </div>
            </div>

        </div>

        <div class="title">
            ใบเสนอราคา
        </div>

        <table class="no-border">
            <tr>
                <td>
                    <strong>เลขที่:</strong>
                    {{ $quotation->quotation_no }}
                </td>

                <td class="text-right">
                    <strong>วันที่:</strong>
                    {{ $quotation->quotation_date }}
                </td>
            </tr>

            <tr>
                <td colspan="2">
                    <strong>ลูกค้า:</strong>
                    {{ \App\Support\DocumentSnapshotValue::resolve(
                        $quotation->customer_name_snapshot,
                        $quotation->customer?->name,
                        'ลูกค้าทั่วไป'
                    ) }}
                </td>
            </tr>

            <tr>
                <td colspan="2">
                    <strong>หมายเหตุ:</strong>
                    {{ $quotation->remark ?? '-' }}
                </td>
            </tr>
        </table>

        <table>
            <thead>
                <tr>
                    <th width="50">ลำดับ</th>
                    <th>สินค้า</th>
                    <th width="100">จำนวน</th>
                    <th width="120">ราคา</th>
                    <th width="120">รวม</th>
                </tr>
            </thead>

            <tbody>
                @foreach ($quotation->items as $index => $item)
                    <tr>
                        <td class="text-center">
                            {{ $index + 1 }}
                        </td>

                        <td>
                            {{ \App\Support\DocumentSnapshotValue::resolve(
                                $item->product_name_snapshot,
                                $item->product?->name
                            ) }}
                        </td>

                        <td class="text-right">
                            {{ $item->qty }}
                        </td>

                        <td class="text-right">
                            {{ number_format($item->selling_price, 2) }}
                        </td>

                        <td class="text-right">
                            {{ number_format($item->total, 2) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>

            <tfoot>
                <tr>
                    <td colspan="4" class="text-right total">
                        รวมทั้งหมด
                    </td>

                    <td class="text-right total">
                        {{ number_format($quotation->total_amount, 2) }}
                    </td>
                </tr>
            </tfoot>
        </table>

        <div class="signature">

            <div class="signature-box">
                ___________________________<br>
                ผู้เสนอราคา
            </div>
            @if ($qrBase64)
                <div class="qr-box">

                    <img src="{{ $qrBase64 }}" class="qr">

                    <br>

                    QR Payment

                </div>
            @endif
            <div class="signature-box">
                ___________________________<br>
                ผู้รับใบเสนอราคา
            </div>



        </div>

    </div>

</body>

</html>
