@extends('adminlte::page')

@section('title', 'นำเข้าสมาชิกจาก Excel')

@section('content_header')
    <div class="d-flex align-items-center justify-content-between">
        <div>
            <h1 class="mb-1">นำเข้าสมาชิกจาก Excel</h1>
            <p class="text-muted mb-0">รองรับ C2M POS และ ATRILAK Customer Template</p>
        </div>
        <a href="{{ route('customers.index') }}" class="btn btn-light">กลับหน้าลูกค้า</a>
    </div>
@stop

@section('content')
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <p class="mb-2">อัปโหลดเพื่ออ่าน ตรวจสอบ Duplicate และ Preview ก่อนบันทึก Customer จริง</p>
            <ul class="text-muted pl-4">
                <li>รองรับไฟล์ Excel Workbook (.xlsx) เท่านั้น</li>
                <li>ไฟล์ C2M ที่เป็น .xls หรือ HTML Web Export ให้เปิดด้วย Excel แล้ว Save As เป็น .xlsx</li>
                <li>ขั้น Preview ไม่สร้าง Customer, ที่อยู่ หรือธุรกรรมใด ๆ</li>
            </ul>
            <div class="mb-3">
                <a href="{{ route('customers.import.template') }}" class="btn btn-outline-primary">ดาวน์โหลด ATRILAK Template</a>
                <a href="{{ route('customers.import.history') }}" class="btn btn-outline-secondary ml-1">ประวัติการนำเข้า</a>
            </div>
            <form method="POST" action="{{ route('customers.import.preview') }}" enctype="multipart/form-data">
                @csrf
                <div class="form-group">
                    <label for="customer-import-file">ไฟล์สมาชิก (.xlsx)</label>
                    <input id="customer-import-file" type="file" name="file" accept=".xlsx" class="form-control-file" required>
                    <small class="form-text text-muted">สูงสุด {{ config('customer_import.max_rows') }} รายการ และ {{ config('customer_import.max_file_size_kb') / 1024 }} MB</small>
                </div>
                <button type="submit" class="btn btn-success">อัปโหลดและ Preview</button>
            </form>
        </div>
    </div>
@stop
