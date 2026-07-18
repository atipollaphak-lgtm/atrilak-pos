@extends('adminlte::page')

@section('title', 'ประวัติการขาย')

@section('content_header')
    <h1>ประวัติการขาย</h1>
@stop

@section('content')

    <div class="card">

        <div class="card-header">
            รายการขายล่าสุด
        </div>

        <div class="card-body">

            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>เลขที่บิล</th>
                        <th>วันที่</th>
                        <th>ลูกค้า</th>
                        <th>ยอดรวม</th>
                        <th>วิธีชำระเงิน</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($sales as $sale)
                        <tr>
                            <td>{{ $sale->id }}</td>
                            <td>{{ $sale->sale_no }}</td>
                            <td>{{ $sale->sale_date }}</td>
                            <td>{{ $sale->customer->name ?? 'ลูกค้าทั่วไป' }}</td>
                            <td>{{ number_format($sale->total_amount, 2) }}</td>
                            <td>{{ \App\Support\SalePaymentDisplay::label($sale->payment_method) }}</td>
                            <td>
                                <a href="{{ route('sales.show', $sale->id) }}" class="btn btn-info btn-sm">
                                    ดูบิล
                                </a>



                                <a href="{{ route('sales.edit', $sale->id) }}" class="btn btn-warning btn-sm">
                                    แก้ไข
                                </a>

                                <form action="{{ route('sales.destroy', $sale->id) }}" method="POST"
                                    style="display:inline-block;"
                                    onsubmit="return confirm('ยืนยันลบบิลนี้? สต๊อกจะถูกคืนกลับ');">

                                    @csrf
                                    @method('DELETE')

                                    <button type="submit" class="btn btn-danger btn-sm">
                                        ลบ
                                    </button>
                                </form>

                                <div class="btn-group">

                                    <button type="button" class="btn btn-sm btn-dark dropdown-toggle"
                                        data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        พิมพ์
                                    </button>

                                    <div class="dropdown-menu">

                                        <a class="dropdown-item" target="_blank"
                                            href="{{ route('sales.invoice-v2', [$sale->id, 'document_type' => 'delivery-note']) }}">
                                            ใบส่งของ
                                        </a>

                                        <a class="dropdown-item" target="_blank"
                                            href="{{ route('sales.invoice-v2', [$sale->id, 'document_type' => 'tax-invoice']) }}">
                                            ใบกำกับภาษี </a>

                                            <a class="dropdown-item" target="_blank"
                                                href="{{ route('sales.invoice-v2', [$sale->id, 'document_type' => 'quotation']) }}">
                                                ใบเสนอราคา </a>

                                    </div>

                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

        </div>

    </div>

@stop
