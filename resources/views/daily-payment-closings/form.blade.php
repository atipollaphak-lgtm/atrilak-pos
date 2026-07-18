@extends('adminlte::page')

@section('title', 'ตรวจนับและปิดยอดประจำวัน')

@section('content_header')
    <h1>ตรวจนับและปิดยอดประจำวัน</h1>
@endsection

@section('content')
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @if ($summary['unrecorded_count'] > 0)
        <div class="alert alert-danger">
            <strong>ยังปิดยอดไม่ได้:</strong> พบข้อมูลการชำระเงินไม่สมบูรณ์ {{ $summary['unrecorded_count'] }} รายการ
        </div>
    @endif
    @if (! $hasSavedActualAmounts)
        <div class="alert alert-warning">กรุณาบันทึกยอดตรวจนับจริงก่อนยืนยันปิดยอด</div>
    @endif

    <div class="card">
        <div class="card-header">สรุปยอดสด ณ วันที่ {{ $dailyPaymentClosing->business_date }}</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3"><strong>เงินสดคาดหวัง:</strong> {{ number_format($summary['cash_total'], 2) }}</div>
                <div class="col-md-4 mb-3"><strong>PromptPay คาดหวัง:</strong> {{ number_format($summary['promptpay_total'], 2) }}</div>
                <div class="col-md-4 mb-3"><strong>ยอดชำระที่บันทึก:</strong> {{ number_format($summary['recorded_total'], 2) }}</div>
                <div class="col-md-4"><strong>เงินที่รับ:</strong> {{ number_format($summary['received_total'], 2) }}</div>
                <div class="col-md-4"><strong>เงินทอน:</strong> {{ number_format($summary['change_total'], 2) }}</div>
                <div class="col-md-4">
                    <strong>จำนวนบิล:</strong> เงินสด {{ $summary['cash_count'] }} |
                    PromptPay {{ $summary['promptpay_count'] }} | ผสม {{ $summary['mixed_count'] }}
                </div>
            </div>
        </div>
    </div>

    @if ($summary['unrecorded_count'] > 0)
        <div class="card card-outline card-danger">
            <div class="card-header">รายการชำระเงินที่ต้องแก้ไข</div>
            <ul class="list-group list-group-flush">
                @foreach ($summary['exceptions'] as $exception)
                    <li class="list-group-item"><strong>{{ $exception['sale']->sale_no }}</strong> — {{ $exception['reason'] }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card">
        <div class="card-header">ยอดตรวจนับจริง</div>
        <form method="POST" action="{{ route('daily-payment-closings.update', $dailyPaymentClosing) }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="revision" value="{{ $dailyPaymentClosing->revision }}">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <label for="actual_cash_amount">เงินสดจริง</label>
                        <input id="actual_cash_amount" name="actual_cash_amount" type="text" inputmode="decimal" pattern="\d+\.\d{2}" class="form-control @error('actual_cash_amount') is-invalid @enderror" value="{{ old('actual_cash_amount', $dailyPaymentClosing->actual_cash_amount) }}" required>
                        @error('actual_cash_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <small class="text-muted">รูปแบบ 0.00</small>
                    </div>
                    <div class="col-md-4">
                        <label for="actual_promptpay_amount">PromptPay จริง</label>
                        <input id="actual_promptpay_amount" name="actual_promptpay_amount" type="text" inputmode="decimal" pattern="\d+\.\d{2}" class="form-control @error('actual_promptpay_amount') is-invalid @enderror" value="{{ old('actual_promptpay_amount', $dailyPaymentClosing->actual_promptpay_amount) }}" required>
                        @error('actual_promptpay_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <small class="text-muted">รูปแบบ 0.00</small>
                    </div>
                    <div class="col-md-4">
                        <label>ผลต่างจากค่าที่บันทึกล่าสุด</label>
                        <div class="form-control-plaintext text-warning">เงินสด {{ number_format($cashVariancePreview, 2) }} | PromptPay {{ number_format($promptpayVariancePreview, 2) }}</div>
                    </div>
                    <div class="col-md-12 mt-3">
                        <label for="notes">หมายเหตุ</label>
                        <textarea id="notes" name="notes" rows="3" class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $dailyPaymentClosing->notes) }}</textarea>
                        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
            <div class="card-footer"><button type="submit" class="btn btn-primary">บันทึกยอดตรวจนับ</button></div>
        </form>
    </div>

    <div class="card card-outline card-success">
        <div class="card-header">ยืนยันการปิดยอด</div>
        <div class="card-body">
            <p class="mb-0">การปิดยอดจะคำนวณยอดคาดหวังใหม่จาก service และเก็บ snapshot ของรายการขาย ณ เวลายืนยัน</p>
        </div>
        <div class="card-footer">
            <form method="POST" action="{{ route('daily-payment-closings.finalize', $dailyPaymentClosing) }}" onsubmit="return confirm('ยืนยันปิดยอดวันที่ {{ $dailyPaymentClosing->business_date }} ด้วยเงินสดจริง {{ $dailyPaymentClosing->actual_cash_amount }} และ PromptPay จริง {{ $dailyPaymentClosing->actual_promptpay_amount }}?');">
                @csrf
                <input type="hidden" name="revision" value="{{ $dailyPaymentClosing->revision }}">
                <button type="submit" class="btn btn-success" @disabled($summary['unrecorded_count'] > 0 || ! $hasSavedActualAmounts)>ยืนยันปิดยอด</button>
            </form>
        </div>
    </div>

    <a href="{{ route('daily-payment-closings.index') }}" class="btn btn-default">กลับประวัติ</a>
@endsection
