@php
    $paymentRows ??= [];
@endphp

@if ($paymentRows !== [])
    <div class="{{ $paymentClass ?? '' }}">
        @if ($showPaymentLabel ?? true)
            <div><strong>วิธีชำระเงิน:</strong> {{ \App\Support\SalePaymentDisplay::label($sale->payment_method) }}</div>
        @endif
        @foreach ($paymentRows as $paymentRow)
            <div>{{ $paymentRow['label'] }}: {{ number_format($paymentRow['value'], 2) }}</div>
        @endforeach
    </div>
@endif
