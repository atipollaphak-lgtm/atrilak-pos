@extends('adminlte::page')

@section('title', 'นำเข้าสินค้าจาก Excel')

@section('content_header')
    <div class="d-flex align-items-center justify-content-between">
        <div>
            <h1 class="mb-1">นำเข้าสินค้าจาก Excel</h1>
            <p class="text-muted mb-0">เพิ่มสินค้าใหม่หลายรายการจาก Template</p>
        </div>
        <a href="{{ route('products.index') }}" class="btn btn-light">กลับหน้าสินค้า</a>
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
            <p>ดาวน์โหลด Template กรอกข้อมูล แล้วอัปโหลดกลับมาเพื่อ Preview ก่อนบันทึกจริง</p>
            <a href="{{ route('products.import.template') }}" class="btn btn-outline-primary mb-3">ดาวน์โหลด Excel Template</a>
            <form method="POST" action="{{ route('products.import.preview') }}" enctype="multipart/form-data">
                @csrf
                <div class="form-group">
                    <label for="product-import-file">ไฟล์ Excel (.xlsx)</label>
                    <input id="product-import-file" type="file" name="file" accept=".xlsx" class="form-control-file" required>
                    <small class="form-text text-muted">สูงสุด {{ config('product_import.max_rows') }} รายการ และ {{ config('product_import.max_file_size_kb') / 1024 }} MB</small>
                </div>
                <button type="submit" class="btn btn-success">อัปโหลดและ Preview</button>
            </form>
        </div>
    </div>
@stop
