@extends('adminlte::page')

@section('title', 'หมวดสินค้า')

@section('content_header')
<h1>หมวดสินค้า</h1>
@stop

@section('content')
@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card">
    <div class="card-header">
        <form method="POST" action="{{ route('categories.store') }}">
            @csrf
            <div class="row align-items-end">
                <div class="col-md-3">
                    <label>ชื่อหมวดสินค้า</label>
                    <input type="text" name="name" class="form-control" placeholder="ชื่อหมวดสินค้า" required>
                </div>
                <div class="col-md-2">
                    <label>Code Prefix</label>
                    <input type="text" name="code_prefix" class="form-control" placeholder="เช่น CEM" maxlength="20">
                </div>
                <div class="col-md-2">
                    <label>Barcode Prefix</label>
                    <input type="text" name="barcode_prefix" class="form-control" placeholder="เช่น 001" maxlength="3">
                </div>
                <div class="col-md-3">
                    <label>การปัดเศษเมื่อขายตามโซน</label>
                    <select name="rounding_override" class="form-control">
                        <option value="">ใช้ค่าของโซน</option>
                        @foreach($roundingOverrides as $increment)
                            <option value="{{ $increment }}">{{ number_format($increment, 2) }} บาท</option>
                        @endforeach
                    </select>
                    <small class="text-muted">มีผลเฉพาะการขายแบบจัดส่ง ไม่กระทบราคาปกติหรือกฎราคาหมวดสินค้าเดิม</small>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary">เพิ่มหมวดสินค้า</button>
                </div>
            </div>
        </form>
    </div>

    <div class="card-body">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>ชื่อหมวดสินค้า</th>
                    <th>Code Prefix</th>
                    <th>Barcode Prefix</th>
                    <th>ปัดเศษตามโซน</th>
                    <th width="360">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                @foreach($categories as $category)
                    <tr>
                        <td>{{ $category->id }}</td>
                        <td>{{ $category->name }}</td>
                        <td>{{ $category->code_prefix ?: '—' }}</td>
                        <td>{{ $category->barcode_prefix ?: '—' }}</td>
                        <td>{{ $category->rounding_override ? number_format($category->rounding_override, 2).' บาท' : 'ใช้ค่าของโซน' }}</td>
                        <td>
                            <form action="{{ route('categories.update', $category) }}" method="POST" class="d-inline-flex flex-wrap align-items-center">
                                @csrf
                                @method('PUT')
                                <input type="text" name="name" value="{{ $category->name }}" required>
                                <input type="text" name="code_prefix" value="{{ $category->code_prefix }}" maxlength="20" placeholder="Code Prefix">
                                <input type="text" name="barcode_prefix" value="{{ $category->barcode_prefix }}" maxlength="3" placeholder="Barcode Prefix">
                                <select name="rounding_override" class="form-control d-inline-block" style="width: 150px">
                                    <option value="">ใช้ค่าของโซน</option>
                                    @foreach($roundingOverrides as $increment)
                                        <option value="{{ $increment }}" @selected((string) $category->rounding_override === (string) $increment)>{{ number_format($increment, 2) }} บาท</option>
                                    @endforeach
                                </select>
                                <button class="btn btn-warning btn-sm">แก้ไข</button>
                            </form>
                            <form action="{{ route('categories.destroy', $category) }}" method="POST" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-danger btn-sm" onclick="return confirm('ลบข้อมูล?')">ลบ</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@stop
