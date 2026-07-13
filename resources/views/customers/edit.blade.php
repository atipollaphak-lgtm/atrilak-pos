@extends('adminlte::page')

@section('title', 'แก้ไขลูกค้า')

@section('content_header')
    <h1>แก้ไขลูกค้า</h1>
@stop

@section('content')

<form action="{{ route('customers.update', $customer) }}"
      method="POST">

    @csrf
    @method('PUT')

    <div class="card">

        <div class="card-header">
            ข้อมูลลูกค้า
        </div>

        <div class="card-body">

            <div class="row">

                <div class="col-md-3">
                    <label>รหัสลูกค้า</label>
                    <input type="text"
                           name="code"
                           class="form-control"
                           value="{{ $customer->code }}">
                </div>

                <div class="col-md-4">
                    <label>ชื่อลูกค้า</label>
                    <input type="text"
                           name="name"
                           class="form-control"
                           value="{{ $customer->name }}"
                           required>
                </div>

                <div class="col-md-3">
                    <label>เบอร์โทร</label>
                    <input type="text"
                           name="phone"
                           class="form-control"
                           value="{{ $customer->phone }}">
                </div>

            </div>

            <br>

            <label>ที่อยู่</label>

            <textarea name="address"
                      class="form-control"
                      rows="3">{{ $customer->address }}</textarea>

                        <br>

            <div class="row">

                <div class="col-md-4">
                    <label>เลขประจำตัวผู้เสียภาษี</label>

                    <input type="text"
                           name="tax_number"
                           class="form-control"
                           value="{{ $customer->tax_number }}">
                </div>

                <div class="col-md-4">
                    <label>ประเภทสาขา</label>

                    <select name="branch_type"
                            class="form-control">

                        <option value="สำนักงานใหญ่"
                            {{ $customer->branch_type == 'สำนักงานใหญ่' ? 'selected' : '' }}>
                            สำนักงานใหญ่
                        </option>

                        <option value="สาขา"
                            {{ $customer->branch_type == 'สาขา' ? 'selected' : '' }}>
                            สาขา
                        </option>

                    </select>
                </div>

                <div class="col-md-4">
                    <label>เลขที่สาขา</label>

                    <input type="text"
                           name="branch_number"
                           class="form-control"
                           value="{{ $customer->branch_number }}"
                           placeholder="กรอกเฉพาะกรณีเป็นสาขา">
                </div>

            </div>

            <br>

            <label>หมายเหตุ</label>

            <textarea name="remark"
                      class="form-control"
                      rows="3">{{ $customer->remark }}</textarea>

            <br>

            <label>สถานะ</label>

            <select name="active"
                    class="form-control">

                <option value="1"
                    {{ $customer->active ? 'selected' : '' }}>
                    ใช้งาน
                </option>

                <option value="0"
                    {{ !$customer->active ? 'selected' : '' }}>
                    ไม่ใช้งาน
                </option>

            </select>

            <br>

            <button class="btn btn-success">
                บันทึก
            </button>

            <a href="{{ route('customers.index') }}"
               class="btn btn-secondary">
                กลับ
            </a>

        </div>

    </div>

</form>

@stop
