@extends('adminlte::page')

@section('title', 'ตรวจนับสต็อก')

@section('content_header')
    <h1>ตรวจนับสต็อก</h1>
@stop

@section('content')

@include('partials.flash-messages')

@if ($errors->any())
    <div class="alert alert-danger" role="alert">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@php
    $oldProductIds = old('product_id', ['']);
    $oldActualQuantities = old('actual_qty', ['0']);
    $oldProductIds = is_array($oldProductIds) ? $oldProductIds : [''];
    $oldActualQuantities = is_array($oldActualQuantities) ? $oldActualQuantities : ['0'];
    $rowCount = max(count($oldProductIds), count($oldActualQuantities), 1);
    $productsById = $products->keyBy('id');
@endphp

<div class="card">
    <div class="card-header">บันทึกตรวจนับสต็อก</div>

    <div class="card-body">
        <form action="{{ route('stock-counts.store') }}" method="POST">
            @csrf

            <div class="row">
                <div class="col-md-3">
                    <label>วันที่ตรวจนับ</label>
                    <input
                        type="date"
                        name="count_date"
                        class="form-control"
                        value="{{ old('count_date', date('Y-m-d')) }}"
                        required>
                </div>

                <div class="col-md-9">
                    <label>หมายเหตุ</label>
                    <input
                        type="text"
                        name="remark"
                        class="form-control"
                        value="{{ old('remark') }}"
                        placeholder="เช่น ตรวจนับสิ้นเดือน">
                </div>
            </div>

            <hr>

            <table class="table table-bordered" id="stock-count-table">
                <thead>
                    <tr>
                        <th>สินค้า</th>
                        <th width="150">สต็อกในระบบ</th>
                        <th width="150">นับจริง</th>
                        <th width="150">ผลต่าง</th>
                        <th width="80">ลบ</th>
                    </tr>
                </thead>

                <tbody>
                    @for ($index = 0; $index < $rowCount; $index++)
                        @php
                            $selectedProductId = $oldProductIds[$index] ?? '';
                            $selectedProduct = $productsById->get((int) $selectedProductId);
                            $systemQty = $selectedProduct?->stock_qty ?? '';
                            $actualQty = $oldActualQuantities[$index] ?? '';
                        @endphp
                        <tr>
                            <td>
                                <select name="product_id[]" class="form-control product-select" required>
                                    <option value="">-- เลือกสินค้า --</option>
                                    @foreach ($products as $product)
                                        <option
                                            value="{{ $product->id }}"
                                            data-stock="{{ $product->stock_qty }}"
                                            @selected((string) $selectedProductId === (string) $product->id)>
                                            {{ $product->name }}{{ $product->active ? '' : ' (ปิดขาย)' }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>

                            <td>
                                <input
                                    type="number"
                                    name="system_qty[]"
                                    class="form-control system-qty"
                                    step="0.0001"
                                    value="{{ $systemQty }}"
                                    readonly>
                            </td>

                            <td>
                                <input
                                    type="number"
                                    name="actual_qty[]"
                                    class="form-control actual-qty"
                                    min="0"
                                    step="0.0001"
                                    value="{{ $actualQty }}"
                                    required>
                            </td>

                            <td>
                                <input
                                    type="number"
                                    class="form-control difference"
                                    step="0.0001"
                                    readonly>
                            </td>

                            <td>
                                <button type="button" class="btn btn-danger btn-sm remove-row">ลบ</button>
                            </td>
                        </tr>
                    @endfor
                </tbody>
            </table>

            <button type="button" class="btn btn-secondary" id="add-row">+ เพิ่มสินค้า</button>
            <button type="submit" class="btn btn-primary">บันทึกตรวจนับ</button>
        </form>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">รายการตรวจนับล่าสุด</div>

    <div class="card-body">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>เลขที่</th>
                    <th>วันที่</th>
                    <th>หมายเหตุ</th>
                </tr>
            </thead>

            <tbody>
                @forelse ($stockCounts as $stockCount)
                    <tr>
                        <td>{{ $stockCount->count_no }}</td>
                        <td>{{ $stockCount->count_date }}</td>
                        <td>{{ $stockCount->remark }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="text-center">ยังไม่มีรายการตรวจนับ</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@stop

@section('js')
    <script src="{{ asset('js/modules/stock-count.js') }}"></script>
@stop
