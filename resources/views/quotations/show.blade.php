@extends('adminlte::page')

@section('title', 'รายละเอียดใบเสนอราคา')

@section('content_header')
    <h1>รายละเอียดใบเสนอราคา</h1>
@stop

@section('content')

    <div class="card">

        <div class="card-header">
            <a href="{{ route('quotations.index') }}" class="btn btn-secondary btn-sm">
                กลับ
            </a>

            <a href="{{ route('quotations.print', $quotation) }}" target="_blank" class="btn btn-success btn-sm">
                พิมพ์
            </a>
            @if ($quotation->status !== 'converted')
                <form action="{{ route('quotations.convert', $quotation) }}" method="POST" style="display:inline-block;">

                    @csrf

                    <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('แปลงเป็นใบขาย ?')">

                        แปลงเป็นใบขาย

                    </button>

                </form>
            @else
                <span class="badge badge-success">
                    แปลงเป็นใบขายแล้ว
                </span>
            @endif
        </div>

        <div class="card-body">

            <p><strong>เลขที่:</strong> {{ $quotation->quotation_no }}</p>
            <p><strong>วันที่:</strong> {{ $quotation->quotation_date }}</p>
            <p><strong>ลูกค้า:</strong> {{ $quotation->customer->name ?? 'ลูกค้าทั่วไป' }}</p>
            <p><strong>หมายเหตุ:</strong> {{ $quotation->remark }}</p>

            <table class="table table-bordered">

                <thead>
                    <tr>
                        <th>สินค้า</th>
                        <th width="100">จำนวน</th>
                        <th width="150">ราคา</th>
                        <th width="150">รวม</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($quotation->items as $item)
                        <tr>
                            <td>{{ $item->product->name ?? '-' }}</td>
                            <td>{{ $item->qty }}</td>
                            <td>{{ number_format($item->selling_price, 2) }}</td>
                            <td>{{ number_format($item->total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>

                <tfoot>
                    <tr>
                        <th colspan="3" class="text-right">
                            รวมทั้งหมด
                        </th>
                        <th>
                            {{ number_format($quotation->total_amount, 2) }}
                        </th>
                    </tr>
                </tfoot>

            </table>

        </div>

    </div>

@stop
