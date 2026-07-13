@extends('adminlte::page')

@section('title', 'สินค้า')

@section('content_header')
    <h1>สินค้า</h1>
@stop

@section('content')

    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            เพิ่มสินค้า
        </div>

        <div class="card-body">

            <form action="{{ route('products.store') }}" method="POST">

                @csrf

                <div class="row">

                    <div class="col-md-3">
                        <label>Barcode</label>
                        <input type="text" name="barcode" class="form-control">
                    </div>

                    <div class="col-md-3">
                        <label>ชื่อสินค้า</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>

                    <div class="col-md-3">
                        <label>หมวดสินค้า</label>
                        <select name="category_id" class="form-control" required>

                            <option value="">เลือกหมวด</option>

                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}">
                                    {{ $category->name }}
                                </option>
                            @endforeach

                        </select>
                    </div>

                    <div class="col-md-3">
                        <label>หน่วยนับ</label>
                        <select name="unit_id" class="form-control">

                            <option value="">
                                เลือกหน่วย
                            </option>

                            @foreach ($units as $unit)
                                <option value="{{ $unit->id }}">
                                    {{ $unit->name }}
                                </option>
                            @endforeach

                        </select>
                    </div>

                </div>

                <br>

                <div class="row">

                    <div class="col-md-3">
                        <label>ต้นทุน</label>
                        <input type="number" step="0.01" name="cost_price" class="form-control">
                    </div>

                    <div class="col-md-3">
                        <label>ราคาขาย</label>
                        <input type="number" step="0.01" name="selling_price" class="form-control">
                    </div>

                    <div class="col-md-3">
                        <label>สต๊อกเริ่มต้น</label>
                        <input type="number" name="stock_qty" class="form-control">
                    </div>

                    <div class="col-md-3">
                        <label>สต๊อกขั้นต่ำ</label>
                        <input type="number" name="minimum_stock" class="form-control">
                    </div>

                </div>

                <br>

                <div class="row">

                    <div class="col-md-3">
                        <label>VAT</label>

                        <select name="vat_enabled" class="form-control">

                            <option value="0">
                                ไม่คิด VAT
                            </option>

                            <option value="1">
                                คิด VAT
                            </option>

                        </select>
                    </div>

                    <div class="col-md-3">
                        <label>สถานะ</label>

                        <select name="active" class="form-control">

                            <option value="1">
                                ใช้งาน
                            </option>

                            <option value="0">
                                ไม่ใช้งาน
                            </option>

                        </select>
                    </div>

                </div>

                <br>

                <div class="row">
                    <div class="col-md-12">

                        <label>หมายเหตุ</label>

                        <textarea name="remark" class="form-control" rows="3"></textarea>

                    </div>
                </div>

                <br>

                <button type="submit" class="btn btn-success">

                    บันทึกสินค้า

                </button>

                <br>



            </form>

        </div>
    </div>

    <div class="card mt-3">

        <div class="card-header">
            รายการสินค้า
        </div>

        <div class="card-body">

            <table class="table table-bordered">

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Barcode</th>
                        <th>ชื่อสินค้า</th>
                        <th>หมวด</th>
                        <th>หน่วย</th>
                        <th>ต้นทุน</th>
                        <th>ราคาขาย</th>
                        <th>คงเหลือ</th>
                        <th>ขั้นต่ำ</th>
                        <th>สถานะ</th>
                        <th width="250">จัดการ</th>
                    </tr>
                </thead>

                <tbody>

                    @foreach ($products as $product)
                        <tr>

                            <td>{{ $product->id }}</td>

                            <td>{{ $product->barcode }}</td>

                            <td>{{ $product->name }}</td>

                            <td>{{ $product->category->name }}</td>

                            <td>
                                {{ $product->unitRelation->name ?? '-' }}
                            </td>

                            <td>
                                {{ number_format($product->cost_price, 2) }}
                            </td>

                            <td>
                                {{ number_format($product->selling_price, 2) }}
                            </td>

                            <td>

                                @if ($product->stock_qty <= $product->minimum_stock)
                                    <span class="badge badge-danger">
                                        {{ $product->stock_qty }}
                                    </span>
                                @else
                                    <span class="badge badge-success">
                                        {{ $product->stock_qty }}
                                    </span>
                                @endif

                            </td>

                            <td>
                                {{ $product->minimum_stock }}
                            </td>
                            <td>

                                @if ($product->active)
                                    <span class="badge badge-success">
                                        ใช้งาน
                                    </span>
                                @else
                                    <span class="badge badge-danger">
                                        ปิดใช้งาน
                                    </span>
                                @endif

                            </td>
                            <td>

                                <a href="{{ route('products.edit', $product) }}" class="btn btn-warning btn-sm">
                                    แก้ไข
                                </a>

                                @if ($product->active)
                                    <form action="{{ route('products.destroy', $product) }}" method="POST"
                                        class="d-inline">

                                        @csrf
                                        @method('DELETE')

                                        <button type="submit" class="btn btn-danger btn-sm"
                                            onclick="return confirm('ต้องการปิดใช้งานสินค้านี้หรือไม่ ?')">

                                            ปิดใช้งาน

                                        </button>

                                    </form>
                                @endif

                                @if (!$product->active)
                                    <form action="{{ route('products.restore', $product->id) }}" method="POST"
                                        style="display:inline-block;">

                                        @csrf

                                        <button class="btn btn-success btn-sm">
                                            เปิดใช้งาน
                                        </button>

                                    </form>
                                @endif
                            </td>

                        </tr>
                    @endforeach

                </tbody>

            </table>

        </div>
    </div>

@stop
