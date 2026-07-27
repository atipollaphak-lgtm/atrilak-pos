@extends('adminlte::page')

@section('title', 'ประวัติการขาย')

@section('content_header')
    <h1>ประวัติการขาย</h1>
@stop

@section('js')
    <script src="{{ asset('js/modules/sale-void.js') }}"></script>
@stop

@section('content')
    @include('partials.flash-messages')

    @php
        $canVoidSales = in_array(auth()->user()?->role, ['manager', 'owner'], true);
    @endphp

    <div class="card">
        <div class="card-header">รายการขายล่าสุด</div>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>เลขที่บิล</th>
                            <th>วันที่</th>
                            <th>ลูกค้า</th>
                            <th>ยอดรวม</th>
                            <th>วิธีชำระเงิน</th>
                            <th>สถานะ</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($sales as $sale)
                            <tr @class(['table-danger' => $sale->isVoided()])>
                                <td>{{ $sale->id }}</td>
                                <td>{{ $sale->sale_no }}</td>
                                <td>{{ $sale->sale_date }}</td>
                                <td>{{ $sale->customer->name ?? 'ลูกค้าทั่วไป' }}</td>
                                <td>{{ number_format($sale->total_amount, 2) }}</td>
                                <td>{{ \App\Support\SalePaymentDisplay::label($sale->payment_method) }}</td>
                                <td>
                                    @if ($sale->isVoided())
                                        <span class="badge badge-danger">ยกเลิก</span>
                                    @else
                                        <span class="badge badge-success">ปกติ</span>
                                    @endif
                                </td>
                                <td class="text-nowrap">
                                    <a href="{{ route('sales.show', $sale) }}" class="btn btn-info btn-sm">ดูบิล</a>

                                    @if ($sale->isActive())
                                        <a href="{{ route('sales.edit', $sale) }}" class="btn btn-warning btn-sm">แก้ไข</a>

                                        @if ($canVoidSales)
                                            <button type="button" class="btn btn-danger btn-sm" data-toggle="modal"
                                                data-target="#voidSaleModal{{ $sale->id }}">
                                                ยกเลิกบิล
                                            </button>
                                        @endif
                                    @endif

                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-dark dropdown-toggle" data-toggle="dropdown"
                                            aria-haspopup="true" aria-expanded="false">
                                            พิมพ์
                                        </button>

                                        <div class="dropdown-menu">
                                            <a class="dropdown-item" target="_blank"
                                                href="{{ route('sales.invoice-v2', [$sale, 'document_type' => 'delivery-note']) }}">
                                                ใบส่งของ
                                            </a>
                                            <a class="dropdown-item" target="_blank"
                                                href="{{ route('sales.invoice-v2', [$sale, 'document_type' => 'tax-invoice']) }}">
                                                ใบกำกับภาษี
                                            </a>
                                            <a class="dropdown-item" target="_blank"
                                                href="{{ route('sales.invoice-v2', [$sale, 'document_type' => 'quotation']) }}">
                                                ใบเสนอราคา
                                            </a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if ($canVoidSales)
        @foreach ($sales as $sale)
            @if ($sale->isActive())
                @include('sales.partials.void-sale-modal', ['sale' => $sale])
            @endif
        @endforeach
    @endif
@stop
