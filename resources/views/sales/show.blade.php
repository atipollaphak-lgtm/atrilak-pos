@extends('adminlte::page')

@section('title', 'รายละเอียดบิลขาย')

@section('content_header')
    <h1>รายละเอียดบิลขาย</h1>
@stop

@section('content')

    @include('partials.flash-messages')

    <div class="card">

        <div class="card-header">
            บิลเลขที่: {{ $sale->sale_no }}
        </div>

        <div class="card-body">

            <p>
                <strong>วันที่:</strong>
                {{ $sale->sale_date }}
            </p>

            <p>
                <strong>ลูกค้า:</strong>
                {{ $sale->customer->name ?? 'ลูกค้าทั่วไป' }}
            </p>
            <p>
                <strong>ช่าง:</strong>
                {{ optional($sale->technician)->name ?? 'ไม่ระบุ' }}
            </p>

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

                <h5>
                    รวมสินค้า :
                    {{ number_format($sale->total_amount - ($sale->delivery_fee ?? 0) + ($sale->discount ?? 0), 2) }}
                    บาท
                </h5>

                <h5>
                    ค่าขนส่ง :
                    {{ number_format($sale->delivery_fee ?? 0, 2) }}
                    บาท
                </h5>

                <h5>
                    ส่วนลด :
                    {{ number_format($sale->discount ?? 0, 2) }}
                    บาท
                </h5>

                <h4 class="text-primary">
                    ยอดสุทธิ :
                    {{ number_format($sale->total_amount, 2) }}
                    บาท
                </h4>

            </div>

            <div class="alert alert-light border mt-3">
                <strong>วิธีชำระเงิน:</strong>
                {{ \App\Support\SalePaymentDisplay::label($sale->payment_method) }}

                @include('sales.partials.payment-details', [
                    'paymentRows' => \App\Support\SalePaymentDisplay::screenRows($sale),
                    'paymentClass' => 'mt-2',
                    'showPaymentLabel' => false,
                ])
            </div>

            <hr>

            <div class="row">

                <div class="col-md-4">

                    <div class="alert alert-info">

                        <strong>ต้นทุนรวม</strong>

                        <br>

                        {{ number_format($totalCost, 2) }}
                        บาท

                    </div>

                </div>

                <div class="col-md-4">

                    <div class="alert alert-success">

                        <strong>กำไรขั้นต้น</strong>

                        <br>

                        {{ number_format($profit, 2) }}
                        บาท

                    </div>

                </div>

            </div>

            <div class="btn-group">

    <button
        type="button"
        class="btn btn-primary dropdown-toggle"
        data-toggle="dropdown"
        aria-haspopup="true"
        aria-expanded="false">

        พิมพ์

    </button>

    <div class="dropdown-menu">

        <a
            class="dropdown-item"
            target="_blank"
            href="{{ route('sales.invoice-v2', [
                $sale->id,
                'document_type' => 'delivery-note',
            ]) }}">

            ใบส่งของ

        </a>

        <a
            class="dropdown-item"
            target="_blank"
            href="{{ route('sales.invoice-v2', [
                $sale->id,
                'document_type' => 'tax-invoice',
            ]) }}">

            ใบกำกับภาษี

        </a>

        <a
            class="dropdown-item"
            target="_blank"
            href="{{ route('sales.invoice-v2', [
                $sale->id,
                'document_type' => 'quotation',
            ]) }}">

            ใบเสนอราคา

        </a>

    </div>

</div>
            <a href="{{ route('sales.edit', $sale->id) }}" class="btn btn-warning">
                แก้ไขบิล
            </a>

            <form action="{{ route('sales.destroy', $sale->id) }}" method="POST" style="display:inline-block;"
                onsubmit="return confirm('ยืนยันลบบิลนี้? สต๊อกจะถูกคืนกลับ');">

                @csrf
                @method('DELETE')

                <button type="submit" class="btn btn-danger">
                    ลบบิล
                </button>
            </form>
            <a href="{{ route('sales.index') }}" class="btn btn-secondary">
                กลับ
            </a>

        </div>

    </div>

@stop
