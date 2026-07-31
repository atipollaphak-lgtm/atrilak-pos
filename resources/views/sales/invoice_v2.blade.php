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
            table-layout: fixed;
            margin-bottom: 8px;
        }

        .header-company-group {
            width: 65%;
            vertical-align: top;
            padding-right: 8px;
        }

        .header-document-cell {
            width: 35%;
            vertical-align: top;
            padding-left: 8px;
        }

        .header-store-cell {
            padding-right: 6px;
            vertical-align: top;
        }

        .header-document-cell {
            width: 32%;
            vertical-align: top;
            padding-left: 4px;
        }

        .logo {
            width: 56px;
            height: 56px;
            object-fit: contain;
        }

        .header-store-cell {
            padding-right: 14px;
            vertical-align: top;
        }

        .store-name {
            font-size: 23px;
            font-weight: 700;
            letter-spacing: .2px;
            line-height: 1.05;
            margin-bottom: 4px;
            color: #111;
        }

        .store-address,
        .store-contact,
        .store-tax-no {
            font-size: 10.5px;
            line-height: 1.28;
            color: #444;
        }

        .store-contact {
            margin-top: 2px;
        }

        .store-tax-no {
            margin-top: 1px;
        }

        .header-document-cell {
            width: 36%;
            vertical-align: top;
            padding-left: 8px;
        }

        .doc-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
        }

        .doc-title {
            font-size: 19px;
            font-weight: 700;
            letter-spacing: .2px;
            line-height: 1.15;
            white-space: nowrap;
        }

        .doc-copy-label {
            border: 1px solid #444;
            border-radius: 4px;
            padding: 4px 10px;
            background: #fafafa;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
        }

        .doc-info-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border: 1px solid #444;
            border-radius: 5px;
            overflow: hidden;
            font-size: 11px;
        }

        .doc-info-table td {
            padding: 4px 7px;
            border: none;
            border-bottom: 1px solid #d3d3d3;
        }

        .doc-info-table tr:last-child td {
            border-bottom: none;
        }

        .doc-info-label {
            width: 38%;
            background: #f4f4f4;
            border-right: 1px solid #d3d3d3 !important;
            font-weight: 700;
            color: #333;
            white-space: nowrap;
        }

        .doc-info-value {
            font-weight: 700;
            color: #111;
            white-space: nowrap;
        }

        .header-divider {
            border-top: 2px solid #000;
            margin: 14px 0 12px;
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
            margin-top: 12px;
            font-size: 13px;
            border: 1.5px solid #000;
        }

        .items-table th {
            background: #e9e9e9;
            border: 1px solid #000;
            padding: 5px 6px;
            text-align: center;
            font-size: 13px;
            font-weight: 700;
            line-height: 1.2;
        }

        .items-table td {
            border: 0.5px solid #aaa;
            padding: 2px 6px;
            height: 23px;
            vertical-align: middle;
            font-size: 13px;
            line-height: 1.15;
        }

        .items-table tbody tr:nth-child(even) {
            background: #fcfcfc;
        }

        .item-name {
            text-align: left;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-weight: 600;
        }

        .qty,
        .price,
        .total,
        .unit {
            text-align: center;
            white-space: nowrap;
            font-weight: 500;
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

        .payment-summary-table>tbody>tr>td {
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

        .document-payment-details {
            margin: 0 0 12px;
            text-align: left;
            font-size: 12px;
            line-height: 1.5;
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

    @if (($document['type'] ?? null) === 'delivery-note')
        <link rel="stylesheet" href="{{ asset('css/sales-invoice-v2.css') }}">
        @if (($paper ?? 'a4') === 'a5')
            <style>
                @page { size: A5 portrait; margin: 0; }
            </style>
        @endif
    @endif
</head>

<body>

    <div class="print-button no-print">
        <button onclick="window.print()">พิมพ์อีกครั้ง</button>
    </div>

    @php
        $paper = in_array(request()->query('paper', 'a4'), ['a4', 'a5'], true)
            ? request()->query('paper', 'a4')
            : 'a4';
    @endphp

    <div class="invoice paper-{{ $paper }}">

        @if (($document['type'] ?? null) === 'delivery-note')
            @include('sales.invoice_v2.delivery-note')
        @else

        @include('sales.partials.void-document-marker', ['sale' => $sale])

        <div class="watermark">
            ATRILAK CONCRETE
        </div>

        @include('sales.invoice_v2.header')


        @include('sales.invoice_v2.customer')

        @php
            $formatNumber = function ($value) {
                $number = (float) $value;

                return floor($number) == $number ? number_format($number, 0) : number_format($number, 2);
            };
        @endphp

        @include('sales.invoice_v2.items')

        @php
            $subTotal = $sale->items->sum('total');
            $deliveryFee = $sale->delivery_fee ?? 0;
            $discount = $sale->discount ?? 0;
            $grandTotal = $subTotal + $deliveryFee - $discount;
        @endphp

        @include('sales.invoice_v2.summary')



        <div class="footer-note">
            ขอบคุณที่ใช้บริการ
        </div>

        @endif


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
