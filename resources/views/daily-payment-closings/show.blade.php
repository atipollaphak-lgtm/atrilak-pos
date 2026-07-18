@extends('adminlte::page')

@section('title', 'รายละเอียดปิดยอดประจำวัน')

@section('content_header')
    <h1>รายละเอียดปิดยอดประจำวัน</h1>
@endsection

@section('content')
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <span>วันที่ {{ $dailyPaymentClosing->business_date }}</span>
            <span class="badge badge-{{ $dailyPaymentClosing->status === 'finalized' ? 'success' : 'warning' }}">{{ $dailyPaymentClosing->status }}</span>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h5>เงินสด</h5>
                    <dl class="row"><dt class="col-7">คาดหวัง</dt><dd class="col-5 text-right">{{ number_format($dailyPaymentClosing->expected_cash_amount, 2) }}</dd><dt class="col-7">จริง</dt><dd class="col-5 text-right">{{ number_format($dailyPaymentClosing->actual_cash_amount, 2) }}</dd><dt class="col-7">ผลต่าง</dt><dd class="col-5 text-right {{ $dailyPaymentClosing->cash_variance != 0 ? 'text-warning font-weight-bold' : '' }}">{{ number_format($dailyPaymentClosing->cash_variance, 2) }}</dd></dl>
                </div>
                <div class="col-md-6">
                    <h5>PromptPay</h5>
                    <dl class="row"><dt class="col-7">คาดหวัง</dt><dd class="col-5 text-right">{{ number_format($dailyPaymentClosing->expected_promptpay_amount, 2) }}</dd><dt class="col-7">จริง</dt><dd class="col-5 text-right">{{ number_format($dailyPaymentClosing->actual_promptpay_amount, 2) }}</dd><dt class="col-7">ผลต่าง</dt><dd class="col-5 text-right {{ $dailyPaymentClosing->promptpay_variance != 0 ? 'text-warning font-weight-bold' : '' }}">{{ number_format($dailyPaymentClosing->promptpay_variance, 2) }}</dd></dl>
                </div>
            </div>
            <hr>
            <div class="row">
                <div class="col-md-4"><strong>ยอดชำระที่บันทึก:</strong> {{ number_format($dailyPaymentClosing->expected_recorded_sales_amount, 2) }}</div>
                <div class="col-md-4"><strong>เงินที่รับ:</strong> {{ number_format($dailyPaymentClosing->expected_received_cash_amount, 2) }}</div>
                <div class="col-md-4"><strong>เงินทอน:</strong> {{ number_format($dailyPaymentClosing->expected_change_amount, 2) }}</div>
                <div class="col-md-12 mt-2"><strong>จำนวนบิล:</strong> เงินสด {{ $dailyPaymentClosing->cash_sales_count }} | PromptPay {{ $dailyPaymentClosing->promptpay_sales_count }} | ผสม {{ $dailyPaymentClosing->mixed_sales_count }} | ไม่สมบูรณ์ {{ $dailyPaymentClosing->unrecorded_payment_count }}</div>
                <div class="col-md-12 mt-2"><strong>หมายเหตุ:</strong> {{ $dailyPaymentClosing->notes ?: '-' }}</div>
                <div class="col-md-6 mt-2"><strong>เปิดโดย:</strong> {{ $dailyPaymentClosing->openedBy->name ?? '-' }}</div>
                <div class="col-md-6 mt-2"><strong>ปิดยอดโดย:</strong> {{ $dailyPaymentClosing->finalizedBy->name ?? '-' }} {{ optional($dailyPaymentClosing->finalized_at)->format('d/m/Y H:i') }}</div>
                @if ($dailyPaymentClosing->reopened_at)
                    <div class="col-md-12 mt-2"><strong>เปิดใหม่โดย:</strong> {{ $dailyPaymentClosing->reopenedBy->name ?? '-' }} {{ $dailyPaymentClosing->reopened_at->format('d/m/Y H:i') }} — {{ $dailyPaymentClosing->reopen_reason }}</div>
                @endif
                <div class="col-md-12 mt-2"><strong>Revision:</strong> {{ $dailyPaymentClosing->revision }} | <strong>Snapshot sale count:</strong> {{ $dailyPaymentClosing->sales->count() }}</div>
            </div>
        </div>
    </div>

    @if ($dailyPaymentClosing->sales->isNotEmpty())
        <div class="card">
            <div class="card-header">Snapshot references</div>
            <div class="card-body p-0"><table class="table table-sm mb-0"><thead><tr><th>เลขที่บิล</th><th>วิธีชำระ</th><th class="text-right">ยอดขาย</th></tr></thead><tbody>@foreach ($dailyPaymentClosing->sales as $snapshot)<tr><td>{{ $snapshot->sale->sale_no ?? $snapshot->sale_id }}</td><td>{{ $snapshot->payment_method }}</td><td class="text-right">{{ number_format($snapshot->sale_total_amount, 2) }}</td></tr>@endforeach</tbody></table></div>
        </div>
    @endif

    @if ($dailyPaymentClosing->status === 'open')
        <a href="{{ route('daily-payment-closings.edit', $dailyPaymentClosing) }}" class="btn btn-primary">กลับไปแก้ไข</a>
    @else
        <a href="{{ route('daily-payment-closings.print', $dailyPaymentClosing) }}" target="_blank" class="btn btn-secondary">พิมพ์รายงาน</a>
        @if (auth()->user()->role === 'owner')
            <button type="button" class="btn btn-warning" data-toggle="collapse" data-target="#reopen-form">เปิดรายการใหม่</button>
            <div id="reopen-form" class="collapse mt-3">
                <div class="card card-outline card-warning"><div class="card-body"><form method="POST" action="{{ route('daily-payment-closings.reopen', $dailyPaymentClosing) }}" onsubmit="return confirm('ยืนยันเปิดรายการปิดยอดใหม่?');">@csrf<input type="hidden" name="revision" value="{{ $dailyPaymentClosing->revision }}"><div class="form-group"><label for="reason">เหตุผลในการเปิดใหม่</label><textarea id="reason" name="reason" class="form-control @error('reason') is-invalid @enderror" required>{{ old('reason') }}</textarea>@error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror</div><button type="submit" class="btn btn-warning">ยืนยันเปิดใหม่</button></form></div></div>
            </div>
        @endif
    @endif
    <a href="{{ route('daily-payment-closings.index') }}" class="btn btn-default">กลับประวัติ</a>
@endsection
