<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายงานปิดยอดประจำวัน {{ $dailyPaymentClosing->business_date }}</title>
    <style>
        @page { size: A4 portrait; margin: 15mm; }
        body { font-family: sans-serif; color: #000; font-size: 12px; }
        .container { max-width: 180mm; margin: 0 auto; }
        .text-center { text-align: center; } .text-right { text-align: right; }
        .title { font-size: 22px; font-weight: bold; border-top: 2px solid #000; border-bottom: 2px solid #000; padding: 8px 0; margin: 14px 0; }
        table { width: 100%; border-collapse: collapse; margin: 12px 0; } th, td { border: 1px solid #000; padding: 6px; } th { background: #eee; }
        .info td { border: 0; padding: 3px 0; } .signatures { display: flex; justify-content: space-between; margin-top: 50px; } .signatures div { width: 40%; text-align: center; }
        .no-print { text-align: right; margin-bottom: 10px; } @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="no-print"><button onclick="window.print()">พิมพ์</button></div>
        <div class="text-center">
            <h2>{{ $setting->store_name ?? 'ATRILAK POS' }}</h2>
            @if ($setting?->store_address)<div>{{ $setting->store_address }}</div>@endif
            @if ($setting?->store_phone)<div>โทร. {{ $setting->store_phone }}</div>@endif
            <div class="title">รายงานปิดยอดประจำวัน</div>
        </div>
        @if ($drift['has_drift'])
            <div data-drift-status="drift"><strong>คำเตือน: พบการเปลี่ยนแปลงหลังปิดยอด</strong> — เพิ่ม {{ $drift['added_count'] }} / ลบ {{ $drift['removed_count'] }} / แก้ {{ $drift['changed_count'] }}
                @foreach (array_merge($drift['added_sales'], $drift['removed_sales'], $drift['changed_sales']) as $sale) <span>{{ $sale['sale_no'] }}</span> @endforeach
            </div>
        @endif
        <table class="info"><tr><td><strong>วันที่ปิดยอด:</strong> {{ $dailyPaymentClosing->business_date }}</td><td class="text-right"><strong>สถานะ:</strong> {{ $dailyPaymentClosing->status }}</td></tr><tr><td><strong>เปิดโดย:</strong> {{ $dailyPaymentClosing->openedBy->name ?? '-' }}</td><td class="text-right"><strong>ปิดยอดโดย:</strong> {{ $dailyPaymentClosing->finalizedBy->name ?? '-' }} {{ optional($dailyPaymentClosing->finalized_at)->format('d/m/Y H:i') }}</td></tr>@if ($dailyPaymentClosing->reopened_at)<tr><td colspan="2"><strong>เปิดใหม่โดย:</strong> {{ $dailyPaymentClosing->reopenedBy->name ?? '-' }} {{ $dailyPaymentClosing->reopened_at->format('d/m/Y H:i') }} — {{ $dailyPaymentClosing->reopen_reason }}</td></tr>@endif</table>
        <table><thead><tr><th>ช่องทาง</th><th class="text-right">คาดหวัง</th><th class="text-right">จริง</th><th class="text-right">ผลต่าง</th></tr></thead><tbody><tr><td>เงินสด</td><td class="text-right">{{ number_format($dailyPaymentClosing->expected_cash_amount, 2) }}</td><td class="text-right">{{ number_format($dailyPaymentClosing->actual_cash_amount, 2) }}</td><td class="text-right">{{ number_format($dailyPaymentClosing->cash_variance, 2) }}</td></tr><tr><td>PromptPay</td><td class="text-right">{{ number_format($dailyPaymentClosing->expected_promptpay_amount, 2) }}</td><td class="text-right">{{ number_format($dailyPaymentClosing->actual_promptpay_amount, 2) }}</td><td class="text-right">{{ number_format($dailyPaymentClosing->promptpay_variance, 2) }}</td></tr></tbody></table>
        <table><tbody><tr><th>ยอดขายที่บันทึก</th><td class="text-right">{{ number_format($dailyPaymentClosing->expected_recorded_sales_amount, 2) }}</td><th>เงินที่รับ</th><td class="text-right">{{ number_format($dailyPaymentClosing->expected_received_cash_amount, 2) }}</td></tr><tr><th>เงินทอน</th><td class="text-right">{{ number_format($dailyPaymentClosing->expected_change_amount, 2) }}</td><th>จำนวนบิล</th><td class="text-right">เงินสด {{ $dailyPaymentClosing->cash_sales_count }} | PromptPay {{ $dailyPaymentClosing->promptpay_sales_count }} | ผสม {{ $dailyPaymentClosing->mixed_sales_count }}</td></tr><tr><th>รายการชำระไม่สมบูรณ์</th><td colspan="3">{{ $dailyPaymentClosing->unrecorded_payment_count }}</td></tr><tr><th>หมายเหตุ</th><td colspan="3">{{ $dailyPaymentClosing->notes ?: '-' }}</td></tr></tbody></table>
        <div class="signatures"><div>........................................<br>ผู้ตรวจนับ</div><div>........................................<br>ผู้อนุมัติ</div></div>
    </div>
    <script>window.onload = function () { window.print(); };</script>
</body>
</html>
