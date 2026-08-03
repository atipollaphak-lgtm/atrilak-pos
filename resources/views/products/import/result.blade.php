@extends('adminlte::page')

@section('title', 'นำเข้าสินค้าสำเร็จ')

@section('content_header')
    <h1>นำเข้าสินค้าสำเร็จ</h1>
@stop

@section('content')
    <div class="alert alert-success">นำเข้าสินค้าใหม่สำเร็จ {{ $result->productCount }} รายการ</div>
    <div class="card"><div class="card-body"><dl class="row mb-0">
        <dt class="col-sm-5">จำนวนสินค้า</dt><dd class="col-sm-7">{{ $result->productCount }}</dd>
        <dt class="col-sm-5">จำนวน Stock Movement</dt><dd class="col-sm-7">{{ $result->stockMovementCount }}</dd>
        <dt class="col-sm-5">รหัสสินค้าเริ่มต้น</dt><dd class="col-sm-7">{{ $result->firstProductCode ?? '—' }}</dd>
        <dt class="col-sm-5">รหัสสินค้าสุดท้าย</dt><dd class="col-sm-7">{{ $result->lastProductCode ?? '—' }}</dd>
        <dt class="col-sm-5">Import Reference</dt><dd class="col-sm-7">{{ $result->importReference }}</dd>
    </dl></div></div>
    <a href="{{ route('products.index') }}" class="btn btn-primary">ดูรายการสินค้า</a>
@stop
