@extends('adminlte::page')

@section('title', 'รายละเอียดรับสินค้า')
@section('content_header')<h1>รายละเอียดรับสินค้า #{{ $receiving->id }}</h1>@stop

@section('content')
    @include('partials.flash-messages')
    <div class="alert alert-info">เอกสารนี้เป็นประวัติรับสินค้า V2 ระบบยังไม่เปิด Edit/Void เพื่อคงความถูกต้องของ Average Cost และ Stock Movement</div>
    <div class="card"><div class="card-body"><dl class="row"><dt class="col-sm-3">แหล่งที่มา</dt><dd class="col-sm-9">{{ $receiving->display_source }}</dd><dt class="col-sm-3">Supplier</dt><dd class="col-sm-9">{{ $receiving->supplier?->name ?: '-' }}</dd><dt class="col-sm-3">วันที่</dt><dd class="col-sm-9">{{ $receiving->purchase_date ? \Illuminate\Support\Carbon::parse($receiving->purchase_date)->format('Y-m-d') : '-' }}</dd><dt class="col-sm-3">ผู้บันทึก</dt><dd class="col-sm-9">{{ $receiving->creator?->name ?: '-' }}</dd></dl>
        <table class="table table-bordered"><thead><tr><th>สินค้า</th><th>หน่วย</th><th>จำนวน</th><th>Base Qty</th><th>ต้นทุน</th><th>Average Cost ก่อน/หลัง</th><th>Stock ก่อน/หลัง</th></tr></thead><tbody>
            @foreach ($receiving->items as $item)<tr><td>{{ $item->product?->name }}</td><td>{{ $item->unit_name_snapshot ?: $item->product?->unit }}</td><td>{{ $item->qty }}</td><td>{{ $item->base_qty }}</td><td>{{ $item->cost_price }}</td><td>{{ $item->average_cost_before }} / {{ $item->average_cost_after }}</td><td>{{ $item->stock_before }} / {{ $item->stock_after }}</td></tr>@endforeach
        </tbody></table>
        <a href="{{ route('receivings.index') }}" class="btn btn-secondary">กลับประวัติ</a>
    </div></div>
@stop
