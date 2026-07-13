@extends('adminlte::page')

@section('title', 'ผู้จำหน่าย')

@section('content_header')
    <h1>ผู้จำหน่าย</h1>
@stop

@section('content')

    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <div class="card">

        <div class="card-header">
            เพิ่มผู้จำหน่าย
        </div>

        <div class="card-body">

            <form action="{{ route('suppliers.store') }}" method="POST">

                @csrf

                <div class="row">

                    <div class="col-md-3">
                        <label>รหัสผู้จำหน่าย</label>
                        <input type="text" name="code" class="form-control">
                    </div>

                    <div class="col-md-4">
                        <label>ชื่อผู้จำหน่าย</label>
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

                <button type="submit" class="btn btn-success">

                    บันทึกผู้จำหน่าย

                </button>

            </form>

        </div>

    </div>

    <div class="card mt-3">

        <div class="card-header">
            รายการผู้จำหน่าย
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
                        <th>หมายเหตุ</th>
                        <th>สถานะ</th>
                        <th width="250">จัดการ</th>
                    </tr>
                </thead>

                <tbody>

                    @foreach ($suppliers as $supplier)
                        <tr>

                            <td>{{ $supplier->id }}</td>

                            <td>{{ $supplier->code }}</td>

                            <td>{{ $supplier->name }}</td>

                            <td>{{ $supplier->phone }}</td>
                            <td>{{ $supplier->address }}</td>

                            <td>{{ $supplier->remark }}</td>

                            <td>

                                @if ($supplier->active)
                                    <span class="badge badge-success">
                                        ใช้งาน
                                    </span>
                                @else
                                    <span class="badge badge-danger">
                                        ไม่ใช้งาน
                                    </span>
                                @endif

                            </td>
                            <td>


                                <a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-warning btn-sm">

                                    แก้ไข

                                </a>
                                <form action="{{ route('suppliers.destroy', $supplier) }}" method="POST" class="d-inline">

                                    @csrf
                                    @method('DELETE')

                                    <button type="submit" class="btn btn-danger btn-sm">

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
