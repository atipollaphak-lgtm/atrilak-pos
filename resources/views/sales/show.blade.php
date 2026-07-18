@extends('adminlte::page')

@section('title', 'รายละเอียดบิลขาย')

@section('content_header')
    <h1>รายละเอียดบิลขาย</h1>
@stop

@section('js')
    <script src="{{ asset('js/modules/sale-void.js') }}"></script>
@stop

@section('content')
    @include('partials.flash-messages')

    <div class="card">
        <div class="card-header">บิลเลขที่: {{ $sale->sale_no }}</div>

        <div class="card-body">
            @if ($sale->isVoided())
                <div class="alert alert-danger" role="status">
                    <h4 class="alert-heading mb-2">ยกเลิก / VOID</h4>
                    <p class="mb-1"><strong>เหตุผล:</strong> {{ $sale->void_reason }}</p>
                    <p class="mb-0">
                        <strong>ยกเลิกเมื่อ:</strong> {{ optional($sale->voided_at)->format('d/m/Y H:i') ?? 'ไม่ระบุ' }}
                        <span class="ml-2"><strong>โดย:</strong> {{ $sale->voidedBy->name ?? 'ไม่ระบุ' }}</span>
                    </p>
                </div>
            @endif

            <p><strong>วันที่:</strong> {{ $sale->sale_date }}</p>
            <p><strong>ลูกค้า:</strong> {{ $sale->customer->name ?? 'ลูกค้าทั่วไป' }}</p>
            <p><strong>ช่าง:</strong> {{ optional($sale->technician)->name ?? 'ไม่ระบุ' }}</p>

            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>สินค้า</th>
                        <th>จำนวน</th>
                        <th>ราคา</th>
                        <th>รวม</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sale->items as $item)
                        <tr>
                            <td>{{ $item->product->name ?? '-' }}</td>
                            <td>{{ number_format($item->qty, 2) }}</td>
                            <td>{{ number_format($item->selling_price, 2) }}</td>
                            <td>{{ number_format($item->total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="text-end">
                <h5>รวมสินค้า : {{ number_format($sale->total_amount - ($sale->delivery_fee ?? 0) + ($sale->discount ?? 0), 2) }} บาท</h5>
                <h5>ค่าขนส่ง : {{ number_format($sale->delivery_fee ?? 0, 2) }} บาท</h5>
                <h5>ส่วนลด : {{ number_format($sale->discount ?? 0, 2) }} บาท</h5>
                <h4 class="text-primary">ยอดสุทธิ : {{ number_format($sale->total_amount, 2) }} บาท</h4>
            </div>

            <div class="alert alert-light border mt-3">
                <strong>วิธีชำระเงิน:</strong> {{ \App\Support\SalePaymentDisplay::label($sale->payment_method) }}
                @include('sales.partials.payment-details', [
                    'paymentRows' => \App\Support\SalePaymentDisplay::screenRows($sale),
                    'paymentClass' => 'mt-2',
                    'showPaymentLabel' => false,
                ])
            </div>

            <hr>

            <div class="row">
                <div class="col-md-4">
                    <div class="alert alert-info"><strong>ต้นทุนรวม</strong><br>{{ number_format($totalCost, 2) }} บาท</div>
                </div>
                <div class="col-md-4">
                    <div class="alert alert-success"><strong>กำไรขั้นต้น</strong><br>{{ number_format($profit, 2) }} บาท</div>
                </div>
            </div>

            <div class="btn-group">
                <button type="button" class="btn btn-primary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true"
                    aria-expanded="false">พิมพ์</button>
                <div class="dropdown-menu">
                    <a class="dropdown-item" target="_blank"
                        href="{{ route('sales.invoice-v2', [$sale, 'document_type' => 'delivery-note']) }}">ใบส่งของ</a>
                    <a class="dropdown-item" target="_blank"
                        href="{{ route('sales.invoice-v2', [$sale, 'document_type' => 'tax-invoice']) }}">ใบกำกับภาษี</a>
                    <a class="dropdown-item" target="_blank"
                        href="{{ route('sales.invoice-v2', [$sale, 'document_type' => 'quotation']) }}">ใบเสนอราคา</a>
                </div>
            </div>

            @if ($sale->isActive())
                <a href="{{ route('sales.edit', $sale) }}" class="btn btn-warning">แก้ไขบิล</a>
                @if (in_array(auth()->user()?->role, ['manager', 'owner'], true))
                    <button type="button" class="btn btn-danger" data-toggle="modal"
                        data-target="#voidSaleModal{{ $sale->id }}">ยกเลิกบิล</button>
                @endif
            @endif

            <a href="{{ route('sales.index') }}" class="btn btn-secondary">กลับ</a>
        </div>
    </div>

    @if ($sale->isActive() && in_array(auth()->user()?->role, ['manager', 'owner'], true))
        @include('sales.partials.void-sale-modal', ['sale' => $sale])
    @endif
@stop
