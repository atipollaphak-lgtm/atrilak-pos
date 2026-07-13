@extends('adminlte::page')

@section('title', 'ลูกค้า')

@section('content_header')
    <h1>ลูกค้า</h1>
@stop

@section('content')

    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <div class="card">

        <div class="card-header">
            เพิ่มลูกค้า
        </div>

        <div class="card-body">

            <form action="{{ route('customers.store') }}" method="POST">

                @csrf

                <div class="row">

                    <div class="col-md-3">
                        <label>รหัสลูกค้า</label>
                        <input type="text" name="code" class="form-control">
                    </div>

                    <div class="col-md-4">
                        <label>ชื่อลูกค้า</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>

                    <div class="col-md-3">
                        <label>เบอร์โทร</label>
                        <input type="text" name="phone" class="form-control">
                    </div>

                </div>

                <br>

                <div class="row">

                    <div class="col-md-12">
                        <label>ที่อยู่</label>

                        <textarea name="address" class="form-control" rows="2"></textarea>
                    </div>

                </div>

                <br>

                <div class="row">

                    <div class="col-md-4">
                        <label>เลขประจำตัวผู้เสียภาษี</label>
                        <input type="text" name="tax_number" class="form-control" maxlength="13">
                    </div>

                    <div class="col-md-4">
                        <label>ประเภทสาขา</label>

                        <select name="branch_type" class="form-control">
                            <option value="สำนักงานใหญ่">
                                สำนักงานใหญ่
                            </option>

                            <option value="สาขา">
                                สาขา
                            </option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label>เลขที่สาขา</label>
                        <input type="text" name="branch_number" class="form-control" maxlength="5"
                            placeholder="กรอกเฉพาะกรณีเป็นสาขา">
                    </div>

                </div>

                <br>

                <div class="row">

                    <div class="col-md-12">

                        <hr>

                        <h5>
                            ที่อยู่จัดส่งหลัก
                        </h5>

                    </div>

                    <div class="col-md-4">
                        <label>ชื่อสถานที่</label>
                        <input type="text" name="delivery_name" class="form-control" value="บ้าน">
                    </div>

                    <div class="col-md-4">
                        <label>ชื่อผู้รับ</label>
                        <input type="text" name="receiver_name" class="form-control">
                    </div>

                    <div class="col-md-4">
                        <label>เบอร์ผู้รับ</label>
                        <input type="text" name="receiver_phone" class="form-control">
                    </div>

                    <div class="col-md-12 mt-3">
                        <label>ที่อยู่จัดส่ง</label>

                        <textarea name="delivery_address" class="form-control" rows="2"></textarea>
                    </div>

                    <div class="col-md-6 mt-3">
                        <label>โซนจัดส่ง</label>

                        <select name="delivery_zone_id" class="form-control">

                            <option value="">
                                -- เลือกโซน --
                            </option>

                            @foreach ($deliveryZones as $zone)
                                <option value="{{ $zone->id }}">
                                    {{ $zone->name }}
                                </option>
                            @endforeach

                        </select>

                    </div>

                    <div class="col-md-6 mt-3">
                        <label>จุดสังเกต</label>

                        <input type="text" name="landmark" class="form-control">
                    </div>

                    <div class="col-md-12 mt-3">
                        <label>หมายเหตุที่อยู่จัดส่ง</label>

                        <textarea name="delivery_remark" class="form-control" rows="2"></textarea>
                    </div>

                </div>

                <br>

                <button type="submit" class="btn btn-success">

                    บันทึกลูกค้า

                </button>

            </form>

        </div>

    </div>

    <div class="card mt-3">

        <div class="card-header">
            รายชื่อลูกค้า
        </div>

        <div class="card-body">

            <table class="table table-bordered">

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>รหัส</th>
                        <th>ชื่อ</th>
                        <th>โทรศัพท์</th>
                        <th>ที่อยู่</th>
                        <th>โซนส่งสินค้า</th>
                        <th>หมายเหตุ</th>
                        <th>สถานะ</th>
                        <th width="220">จัดการ</th>
                    </tr>
                </thead>

                <tbody>

                    @foreach ($customers as $customer)
                        <tr>

                            <td>{{ $customer->id }}</td>

                            <td>{{ $customer->code }}</td>

                            <td>{{ $customer->name }}</td>

                            <td>{{ $customer->phone }}</td>
                            <td>{{ $customer->address }}</td>

                            <td>
                                {{ optional(optional($customer->defaultDeliveryAddress)->deliveryZone)->name ?? '-' }}
                            </td>

                            <td>{{ $customer->remark }}</td>

                            <td>
                                @if ($customer->active)
                                    <span class="badge badge-success">ใช้งาน</span>
                                @else
                                    <span class="badge badge-danger">ไม่ใช้งาน</span>
                                @endif
                            </td>
                            <td>

                                <a href="{{ route('customers.edit', $customer) }}" class="btn btn-warning btn-sm">
                                    แก้ไข
                                </a>

                                <a href="{{ route('customers.delivery-addresses.index', $customer) }}"
                                    class="btn btn-info btn-sm">
                                    ที่อยู่จัดส่ง
                                </a>

                                <form action="{{ route('customers.destroy', $customer) }}" method="POST" class="d-inline">

                                    @csrf
                                    @method('DELETE')

                                    <button type="submit" class="btn btn-danger btn-sm"
                                        onclick="return confirm('ต้องการปิดใช้งานลูกค้าหรือไม่ ?')">

                                        ปิดใช้งาน

                                    </button>

                                </form>

                            </td>

                        </tr>
                    @endforeach

                </tbody>

            </table>

        </div>

    </div>

@stop
