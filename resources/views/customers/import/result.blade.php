@extends('adminlte::page')

@section('title', 'นำเข้าสมาชิกสำเร็จ')

@section('content_header')
    <div class="d-flex align-items-center justify-content-between">
        <div>
            <h1 class="mb-1">นำเข้าสมาชิกสำเร็จ</h1>
            <p class="text-muted mb-0">Batch #{{ $result->batchId }}</p>
        </div>
        <a href="{{ route('customers.import.history.show', $result->batchId) }}" class="btn btn-outline-secondary">ดูประวัติ Batch</a>
    </div>
@stop

@section('content')
    <div class="alert alert-success">ดำเนินการนำเข้าสมาชิกเรียบร้อยแล้ว</div>
    <div class="row">
        <div class="col-md-3"><div class="small-box bg-success"><div class="inner"><h3>{{ $result->importedCount }}</h3><p>สร้าง Customer ใหม่</p></div></div></div>
        <div class="col-md-3"><div class="small-box bg-info"><div class="inner"><h3>{{ $result->duplicateCount }}</h3><p>ข้ามข้อมูลซ้ำ</p></div></div></div>
        <div class="col-md-3"><div class="small-box bg-warning"><div class="inner"><h3>{{ $result->reviewCount }}</h3><p>ต้องตรวจสอบ</p></div></div></div>
        <div class="col-md-3"><div class="small-box bg-danger"><div class="inner"><h3>{{ $result->invalidCount }}</h3><p>ผิดพลาด</p></div></div></div>
    </div>
    <p class="text-muted">ไม่ได้เลือก {{ $result->notSelectedCount }} รายการ จากทั้งหมด {{ $result->totalParsed }} รายการ</p>
    <a href="{{ route('customers.index') }}" class="btn btn-primary">กลับหน้าลูกค้า</a>
@stop
