@extends('adminlte::page')

@section('title', 'แก้ไขผู้จำหน่าย')

@section('content_header')
    <h1>แก้ไขผู้จำหน่าย</h1>
@stop

@section('content')

    <form action="{{ route('suppliers.update', $supplier) }}" method="POST">

        @csrf
        @method('PUT')

        <div class="card">

            <div class="card-header">
                ข้อมูลผู้จำหน่าย
            </div>

            <div class="card-body">

                <div class="row">

                    <div class="col-md-3">
                        <label>รหัสผู้จำหน่าย</label>
                        <input type="text" name="code" class="form-control" value="{{ $supplier->code }}">
                    </div>

                    <div class="col-md-4">
                        <label>ชื่อผู้จำหน่าย</label>
                        <input type="text" name="name" class="form-control" value="{{ $supplier->name }}" required>
                    </div>

                    <div class="col-md-3">
                        <label>เบอร์โทร</label>
                        <input type="text" name="phone" class="form-control" value="{{ $supplier->phone }}">
                    </div>

                </div>

                <br>

                <label>ที่อยู่</label>

                <textarea name="address" class="form-control" rows="3">{{ $supplier->address }}</textarea>

                <br>

                <label>หมายเหตุ</label>

                <textarea name="remark" class="form-control" rows="3">{{ $supplier->remark }}</textarea>

                <br>

                <label>สถานะ</label>

                <select name="active" class="form-control">

                    <option value="1" {{ $supplier->active ? 'selected' : '' }}>
                        ใช้งาน
                    </option>

                    <option value="0" {{ !$supplier->active ? 'selected' : '' }}>
                        ไม่ใช้งาน
                    </option>

                </select>

                <br>

                <button class="btn btn-success">
                    บันทึก
                </button>

                <a href="{{ route('suppliers.index') }}" class="btn btn-secondary">
                    กลับ
                </a>

            </div>

        </div>

    </form>

@stop
