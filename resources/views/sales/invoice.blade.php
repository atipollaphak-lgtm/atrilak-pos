<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>ใบส่งของ {{ $sale->sale_no ?? $sale->id }}</title>

    <style>
        @page {
            size: A4;
            margin: 12mm;
        }

        body {
            font-family: "Tahoma", sans-serif;
            font-size: 14px;
            color: #000;
            margin: 0;
            padding: 0;
        }

        .invoice {
            width: 100%;
            position: relative;
        }

        .watermark {
            position: fixed;
            top: 35%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-25deg);
            font-size: 80px;
            font-weight: bold;
            color: rgba(0, 0, 0, 0.03);
            z-index: -1;
            white-space: nowrap;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table td {
            vertical-align: top;
        }

        .logo {
            width: 110px;
            height: 110px;
            object-fit: contain;
        }

        .store-name {
            font-size: 28px;
            font-weight: 700;
            letter-spacing: .5px;
            margin-bottom: 6px;
        }

        .doc-title {
    text-align: right;
    font-size: 20px;
    font-weight: 700;
    line-height: 1.3;
    margin-bottom: 10px;
}

        .doc-box {
            border: 2px solid #000;
            border-radius: 6px;
            padding: 10px 12px;
            font-size: 14px;
            line-height: 1.8;
        }

        .doc-box div {
            display: flex;
            justify-content: space-between;
        }

        .line {
            border-top: 2px solid #000;
            margin: 12px 0;
        }

        .customer-section {
            border: 1px solid #000;
            border-radius: 6px;
            margin-top: 10px;
            overflow: hidden;
        }

        .customer-section-title {
            padding: 7px 10px;
            background: #f2f2f2;
            border-bottom: 1px solid #000;
            font-size: 15px;
            font-weight: bold;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-table td {
            padding: 6px 8px;
            vertical-align: top;
            border-bottom: 1px solid #d7d7d7;
        }

        .info-table tr:last-child td {
            border-bottom: none;
        }

        .info-label {
            width: 15%;
            font-weight: bold;
            white-space: nowrap;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
            font-size: 13px;
        }

        .items-table th {
            background: #e9e9e9;
            border: 1px solid #000;
            padding: 8px 6px;
            text-align: center;
            font-weight: bold;
        }

        .items-table td {
    border: 1px solid #000;
    padding: 6px;
    height: 30px;
    vertical-align: middle;
}

        .items-table tbody tr:nth-child(even) {
            background: #fcfcfc;
        }

        .item-name {
    text-align: left;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

        .qty,
.price,
.total {
    text-align: center;
    white-space: nowrap;
}

        .unit {
            text-align: center;
            white-space: nowrap;
        }

        .text-center {
            text-align: center;
        }

        .text-end {
            text-align: right;
        }

        .payment-summary-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 16px;
    page-break-inside: avoid;
}

.payment-summary-table > tbody > tr > td {
    vertical-align: top;
}

.payment-cell {
    width: 58%;
    text-align: center;
    padding: 4px 20px 0 0;
}

.summary-cell {
    width: 42%;
}

.payment-title {
    font-size: 14px;
    font-weight: bold;
    margin-bottom: 5px;
}

.signature-row {
    margin-top: 20px;
    display: flex;
    justify-content: space-between;
    gap: 16px;
}

.signature-box {
    flex: 1;
    text-align: center;
    font-size: 13px;
}

.signature-line {
    border-top: 1px solid #000;
    margin-bottom: 4px;
}

.summary-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

        .summary-table td {
            border: 1px solid #000;
            padding: 9px 12px;
        }

        .summary-table td:last-child {
            text-align: right;
            width: 42%;
            white-space: nowrap;
        }

        .grand-total {
            background: #f2f2f2;
            font-size: 22px;
            font-weight: bold;
        }

        .grand-total td {
            border-width: 2px;
        }

        .baht-text {
    width: auto;
    margin-left: 0;
            margin-top: 8px;
            border: 1px solid #000;
            padding: 8px;
            text-align: center;
            font-size: 13px;
            font-weight: bold;
            border-radius: 4px;
        }

        .bottom-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 45px;
        }

        .bottom-table td {
            vertical-align: top;
        }

        .signature-cell {
    width: 50%;
    text-align: center;
    vertical-align: bottom;
    padding-top: 10px;
    line-height: 1.2;
}

        .qr-cell {
            width: 30%;
            text-align: center;
            padding-top: 25px;
        }

        .qr-image {
            width: 110px;
            height: 110px;
            object-fit: contain;
        }

        .footer-note {
            margin-top: 20px;
            text-align: center;
            font-size: 13px;
        }

        .print-button {
            margin-bottom: 15px;
            text-align: right;
        }

        .print-button button {
            padding: 8px 18px;
            font-size: 14px;
            cursor: pointer;
        }

        @media print {
            .print-button {
                display: none;
            }

            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>

<body>

    <div class="print-button no-print">
        <button onclick="window.print()">พิมพ์อีกครั้ง</button>
    </div>

    <div class="invoice">

        <div class="watermark">
            ATRILAK CONCRETE
        </div>

        <table class="header-table">
            <tr>
                <td style="width: 15%;">
                    @if (!empty($setting?->logo))
                        <img src="{{ asset('storage/' . $setting->logo) }}" class="logo">
                    @endif
                </td>

                <td style="width: 50%;">
                    <div class="store-name">
                        {{ $setting->store_name ?? 'อตรีลักษณ์ คอนกรีต' }}
                    </div>

                    <div>
                        {{ $setting->store_address ?? 'ที่อยู่ร้าน' }}
                    </div>

                    <div>
                        โทร {{ $setting->store_phone ?? '-' }}
                    </div>

                    @if (!empty($setting?->tax_no))
                        <div>
                            เลขผู้เสียภาษี {{ $setting->tax_no }}
                        </div>
                    @endif
                </td>

                <td style="width: 35%;">
                    <div class="doc-title">
                        ใบส่งของ / ใบเสร็จรับเงิน
                    </div>

                    <div class="doc-box">
                        <div>
                            เลขที่เอกสาร :
                            {{ $sale->sale_no ?? 'SALE-' . str_pad($sale->id, 5, '0', STR_PAD_LEFT) }}
                        </div>

                        <div>
                            วันที่ :
                            {{ \Carbon\Carbon::parse($sale->sale_date)->format('d/m/Y') }}
                        </div>
                    </div>
                </td>
            </tr>
        </table>

        <div class="line"></div>

        <div class="customer-section">
            <div class="customer-section-title">
                ข้อมูลลูกค้าและการจัดส่ง
            </div>

            <table class="info-table">
                <tr>
                    <td class="info-label">
                        ลูกค้า
                    </td>

                    <td style="width: 45%;">
                        {{ $sale->customer->name ?? 'ลูกค้าทั่วไป' }}
                    </td>

                    <td class="info-label">
                        โทร
                    </td>

                    <td style="width: 25%;">
                        {{ $sale->customer->phone ?? '-' }}
                    </td>
                </tr>

                <tr>
                    <td class="info-label">
                        ที่อยู่
                    </td>

                    <td colspan="3">
                        {{ $sale->customer->address ?? '-' }}
                    </td>
                </tr>

                <tr>
                    <td class="info-label">
                        วิธีรับสินค้า
                    </td>

                    <td>
                        @if (($sale->delivery_method ?? '') === 'pickup')
                            🏪 ลูกค้ารับเอง
                        @else
                            🚚 จัดส่ง
                        @endif
                    </td>

                    <td class="info-label">
                        ช่าง
                    </td>

                    <td>
                        @if (!empty($sale->technician))
                            {{ $sale->technician->name ?? '-' }}
                        @else
                            -
                        @endif
                    </td>
                </tr>
            </table>
        </div>

        @php
            $formatNumber = function ($value) {
                $number = (float) $value;

                return floor($number) == $number
                    ? number_format($number, 0)
                    : number_format($number, 2);
            };
        @endphp

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
                            {{ $item->product->name ?? '-' }}
                        </td>

                        <td class="qty">
    {{ $formatNumber($item->qty) }}
</td>

                        <td class="unit">
                            {{ $item->product->unit->name ?? ($item->product->unit ?? '-') }}
                        </td>

                        <td class="price">
                            {{ $formatNumber($item->selling_price) }}
                        </td>

                        <td class="total">
                            {{ $formatNumber($item->total) }}
                        </td>
                    </tr>
                @endforeach

            </tbody>
        </table>

        @php
            $subTotal = $sale->items->sum('total');
            $deliveryFee = $sale->delivery_fee ?? 0;
            $discount = $sale->discount ?? 0;
            $grandTotal = $subTotal + $deliveryFee - $discount;
        @endphp

        <table class="payment-summary-table">
    <tr>
        <td class="payment-cell">
            @if (!empty($setting?->qr_image))
                <div class="payment-title">
                    QR Payment
                </div>

                <img
                    src="{{ asset('storage/' . $setting->qr_image) }}"
                    class="qr-image"
                    alt="QR Payment"
                >

                <div class="payment-note">
    สแกนเพื่อชำระเงิน
</div>

<div class="signature-row">

    <div class="signature-box">
        <div class="signature-line"></div>
        ผู้รับสินค้า
    </div>

    <div class="signature-box">
        <div class="signature-line"></div>
        ผู้ส่งสินค้า
    </div>

</div>
            @endif
        </td>

        <td class="summary-cell">
            <table class="summary-table">
                <tr>
                    <td>รวมสินค้า</td>
                    <td class="text-end">
                        {{ $formatNumber($subTotal) }}
                    </td>
                </tr>

                <tr>
                    <td>ค่าขนส่ง</td>
                    <td class="text-end">
                        {{ $formatNumber($deliveryFee) }}
                    </td>
                </tr>

                <tr>
                    <td>ส่วนลด</td>
                    <td class="text-end">
                        {{ $formatNumber($discount) }}
                    </td>
                </tr>

                <tr class="grand-total">
                    <td>ยอดสุทธิ</td>
                    <td class="text-end">
                        {{ $formatNumber($grandTotal) }}
                    </td>
                </tr>
            </table>

            <div class="baht-text">
                ({{ thaiBahtText($grandTotal) }})
            </div>
        </td>
    </tr>
</table>

        @if (!empty($setting?->qr_code))
            <div class="qr-section">
                <div>QR Payment</div>
                <img src="{{ asset('storage/' . $setting->qr_code) }}">
            </div>
        @endif

        <div class="footer-note">
            ขอบคุณที่ใช้บริการ
        </div>

    </div>
    <script>
        window.onload = function() {

            setTimeout(function() {

                window.print();

            }, 300);

        };
    </script>
</body>

</html>
