@extends('adminlte::page')

@section('title', 'รายละเอียดซื้อเข้า')

@section('content_header')
    <h1>รายละเอียดซื้อเข้า #{{ $purchase->id }}</h1>
@stop

@section('content')

    <div class="card">
        <div class="card-header">
            ข้อมูลการรับเข้าสินค้า
        </div>

        <div class="card-body">

            <table class="table table-bordered">
                <tr>
                    <th width="200">เลขที่รายการ</th>
                    <td>{{ $purchase->id }}</td>
                </tr>

                <tr>
                    <th>วันที่</th>
                    <td>{{ $purchase->purchase_date }}</td>
                </tr>

                <tr>
                    <th>ผู้จำหน่าย</th>
                    <td>{{ $purchase->supplier->name ?? '-' }}</td>
                </tr>

                <tr>
                    <th>ยอดรวม</th>
                    <td>{{ number_format($purchase->total_amount, 2) }} บาท</td>
                </tr>
            </table>

        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header">
            รายการสินค้า
        </div>

        <div class="card-body">

            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>สินค้า</th>
                        <th>จำนวน</th>
                        <th>ต้นทุนต่อหน่วย</th>
                        <th>รวม</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($purchase->items as $item)
                        <tr>
                            <td>{{ $item->product->name ?? '-' }}</td>
                            <td>{{ number_format($item->qty) }}</td>
                            <td>{{ number_format($item->cost_price, 2) }}</td>
                            <td>{{ number_format($item->total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>

                <tfoot>
                    <tr>
                        <th colspan="3" class="text-right">รวมทั้งบิล</th>
                        <th>{{ number_format($purchase->total_amount, 2) }}</th>
                    </tr>
                </tfoot>
            </table>
            <a href="{{ route('purchases.print', $purchase) }}" target="_blank" class="btn btn-primary">

                พิมพ์

            </a>
            <a href="{{ route('purchases.edit', $purchase) }}" class="btn btn-warning">
                แก้ไข
            </a>
            <form action="{{ route('purchases.destroy', $purchase) }}" method="POST" style="display:inline-block;"
                onsubmit="return confirm('ยืนยันลบรายการรับเข้านี้? สต๊อกจะถูกปรับออก');">

                @csrf
                @method('DELETE')

                <button type="submit" class="btn btn-danger">
                    ลบรายการรับเข้า
                </button>
            </form>
            <a href="{{ route('purchases.index') }}" class="btn btn-secondary">
                กลับ
            </a>

        </div>
    </div>

@stop
