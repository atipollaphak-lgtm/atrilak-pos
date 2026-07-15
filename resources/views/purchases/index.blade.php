@extends('adminlte::page')

@section('title', 'ซื้อเข้า')

@section('content_header')
    <h1>ซื้อเข้า</h1>
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
        $oldProductIds = is_array(old('product_id')) ? old('product_id') : [null];
        $oldQuantities = is_array(old('qty')) ? old('qty') : ['1'];
        $oldCostPrices = is_array(old('cost_price')) ? old('cost_price') : [''];
        $rowCount = max(count($oldProductIds), count($oldQuantities), count($oldCostPrices), 1);
    @endphp

    <div class="card">
        <div class="card-header">บันทึกซื้อเข้า</div>

        <div class="card-body">
            <form id="purchase-create-form" class="purchase-form" action="{{ route('purchases.store') }}" method="POST">
                @csrf

                <div class="row">
                    <div class="col-md-4">
                        <label>ผู้จำหน่าย</label>
                        <select name="supplier_id" class="form-control">
                            <option value="">-- เลือกผู้จำหน่าย --</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @selected((string) old('supplier_id') === (string) $supplier->id)>
                                    {{ $supplier->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label>วันที่</label>
                        <input type="date" name="purchase_date" class="form-control"
                            value="{{ old('purchase_date', date('Y-m-d')) }}">
                    </div>
                </div>

                <hr>

                <div class="mb-2">
                    <button type="button" id="addRow" class="btn btn-primary btn-sm">+ เพิ่มรายการ</button>
                </div>

                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th width="40%">สินค้า</th>
                            <th>จำนวน</th>
                            <th>ต้นทุน</th>
                            <th>รวม</th>
                            <th width="80">ลบ</th>
                        </tr>
                    </thead>
                    <tbody id="purchase-items">
                        @for ($index = 0; $index < $rowCount; $index++)
                            <tr>
                                <td>
                                    <select name="product_id[]" class="form-control">
                                        <option value="">-- เลือกสินค้า --</option>
                                        @foreach ($products as $product)
                                            <option value="{{ $product->id }}"
                                                @selected((string) ($oldProductIds[$index] ?? '') === (string) $product->id)>
                                                {{ $product->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input type="number" min="0.0001" step="0.0001" name="qty[]"
                                        class="form-control qty" value="{{ $oldQuantities[$index] ?? '' }}">
                                </td>
                                <td>
                                    <input type="number" min="0.01" step="0.01" name="cost_price[]"
                                        class="form-control cost-price" value="{{ $oldCostPrices[$index] ?? '' }}">
                                </td>
                                <td class="line-total">0.00</td>
                                <td>
                                    <button type="button" class="btn btn-danger btn-sm remove-row">ลบ</button>
                                </td>
                            </tr>
                        @endfor
                    </tbody>
                </table>

                <div class="text-end mb-3">
                    <h4>ยอดรวมทั้งบิล : <span id="grand-total">0.00</span> บาท</h4>
                </div>

                <button type="submit" class="btn btn-success">บันทึกซื้อเข้า</button>
            </form>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header">รายการซื้อเข้าล่าสุด</div>
        <div class="card-body">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>วันที่</th>
                        <th>ผู้จำหน่าย</th>
                        <th>ยอดรวม</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($purchases as $purchase)
                        <tr>
                            <td>{{ $purchase->id }}</td>
                            <td>{{ $purchase->purchase_date }}</td>
                            <td>{{ $purchase->supplier->name ?? '-' }}</td>
                            <td>{{ number_format($purchase->total_amount, 2) }}</td>
                            <td>
                                <a href="{{ route('purchases.show', $purchase) }}" class="btn btn-info btn-sm">ดูรายละเอียด</a>
                                <a href="{{ route('purchases.edit', $purchase) }}" class="btn btn-warning btn-sm">แก้ไข</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@stop

@section('js')
    <script src="{{ asset('js/modules/purchase.js') }}"></script>
@stop
