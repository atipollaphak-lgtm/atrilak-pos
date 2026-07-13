@extends('adminlte::page')

@section('title', 'แก้ไขสินค้า')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/pricing-management.css') }}">
@stop

@section('content_header')
    <h1>แก้ไขสินค้า</h1>
@stop

@section('content')
    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>กรอกข้อมูลไม่ครบ หรือข้อมูลไม่ถูกต้อง</strong>

            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>
                        {{ $error }}
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card">

        <div class="card-header">
            แก้ไขสินค้า: {{ $product->name }}
        </div>

        <div class="card-body">

            <form action="{{ route('products.update', $product) }}" method="POST">

                @csrf
                @method('PUT')

                <div class="row">

                    <div class="col-md-3">
                        <label>Barcode</label>
                        <input type="text" name="barcode" class="form-control"
                            value="{{ old('barcode', $product->barcode) }}">
                    </div>

                    <div class="col-md-3">
                        <label>ชื่อสินค้า</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $product->name) }}"
                            required>
                    </div>

                    <div class="col-md-3">
                        <label>หมวดสินค้า</label>
                        <select name="category_id" class="form-control" required>

                            <option value="">เลือกหมวด</option>

                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}"
                                    {{ old('category_id', $product->category_id) == $category->id ? 'selected' : '' }}>
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
                                <option value="{{ $unit->id }}"
                                    {{ old('unit_id', $product->unit_id) == $unit->id ? 'selected' : '' }}>
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
                        <input type="number" step="0.01" name="cost_price" class="form-control"
                            value="{{ old('cost_price', $product->cost_price) }}">
                    </div>

                    <div class="col-md-3">
                        <label>ราคาขาย</label>
                        <input type="number" step="0.01" name="selling_price" class="form-control"
                            value="{{ old('selling_price', $product->selling_price) }}">
                    </div>


                    <div class="col-md-3">
                        <label>สต๊อก</label>
                        <input type="number" name="stock_qty" class="form-control"
                            value="{{ old('stock_qty', $product->stock_qty) }}">
                    </div>

                    <div class="col-md-3">
                        <label>สต๊อกขั้นต่ำ</label>
                        <input type="number" name="minimum_stock" class="form-control"
                            value="{{ old('minimum_stock', $product->minimum_stock) }}">
                    </div>

                </div>

                <br>

                <div class="row">

                    <div class="col-md-3">
                        <label>VAT</label>

                        <select name="vat_enabled" class="form-control">

                            <option value="0" {{ old('vat_enabled', $product->vat_enabled) == 0 ? 'selected' : '' }}>
                                ไม่คิด VAT
                            </option>

                            <option value="1" {{ old('vat_enabled', $product->vat_enabled) == 1 ? 'selected' : '' }}>
                                คิด VAT
                            </option>

                        </select>
                    </div>

                    <div class="col-md-3">
                        <label>สถานะ</label>

                        <select name="active" class="form-control">

                            <option value="1" {{ old('active', $product->active) == 1 ? 'selected' : '' }}>
                                ใช้งาน
                            </option>

                            <option value="0" {{ old('active', $product->active) == 0 ? 'selected' : '' }}>
                                ไม่ใช้งาน
                            </option>

                        </select>
                    </div>

                </div>

                <br>

                <div class="row">

                    <div class="col-md-12">
                        <label>หมายเหตุ</label>

                        <textarea name="remark" class="form-control" rows="3">{{ old('remark', $product->remark) }}</textarea>
                    </div>

                </div>

                <br>

                <div class="d-flex justify-content-between mt-3">

                    <div>

                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i>
                            บันทึกการแก้ไข
                        </button>

                        <a href="{{ route('products.index') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i>
                            กลับ
                        </a>

                    </div>

            </form>

            <form action="{{ route('products.destroy', $product->id) }}" method="POST"
                onsubmit="return confirm('ยืนยันปิดใช้งานสินค้า?');">

                @csrf
                @method('DELETE')

                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-times-circle"></i>
                    ปิดใช้งานสินค้า
                </button>

            </form>

        </div>

        @include('products.partials._pricing_management')

        @include('products.partials._product_units')
        @include('products.partials._product_price_tier_create_modal')

        @include('products.partials._product_price_tier_edit_modal')

        <hr>

        <div class="card mt-3">

            <div class="card-header bg-info">
                ประวัติราคา
            </div>

            <div class="card-body">

                @if (isset($priceHistories) && $priceHistories->count())

                    <table class="table table-bordered">

                        <thead>
                            <tr>
                                <th>วันที่</th>
                                <th>ต้นทุนเดิม</th>
                                <th>ต้นทุนใหม่</th>
                                <th>ขายเดิม</th>
                                <th>ขายใหม่</th>
                            </tr>
                        </thead>

                        <tbody>

                            @foreach ($priceHistories as $history)
                                <tr>
                                    <td>
                                        {{ $history->created_at->format('d/m/Y H:i') }}
                                    </td>

                                    <td>
                                        {{ number_format($history->old_cost_price, 2) }}
                                    </td>

                                    <td>
                                        {{ number_format($history->new_cost_price, 2) }}
                                    </td>

                                    <td>
                                        {{ number_format($history->old_selling_price, 2) }}
                                    </td>

                                    <td>
                                        {{ number_format($history->new_selling_price, 2) }}
                                    </td>
                                </tr>
                            @endforeach

                        </tbody>

                    </table>
                @else
                    <div class="alert alert-info">
                        ยังไม่มีประวัติราคา
                    </div>

                @endif

            </div>

        </div>
    </div>

    </div>

@stop
