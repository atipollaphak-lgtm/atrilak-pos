@extends('adminlte::page')

@section('title', 'แก้ไขซื้อเข้า')

@section('content_header')
    <h1>แก้ไขซื้อเข้า #{{ $purchase->id }}</h1>
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
        $defaultProductIds = $purchase->items->pluck('product_id')->all();
        $defaultQuantities = $purchase->items->pluck('qty')->all();
        $defaultCostPrices = $purchase->items->pluck('cost_price')->all();
        $oldProductIds = is_array(old('product_id')) ? old('product_id') : $defaultProductIds;
        $oldQuantities = is_array(old('qty')) ? old('qty') : $defaultQuantities;
        $oldCostPrices = is_array(old('cost_price')) ? old('cost_price') : $defaultCostPrices;
        $rowCount = max(count($oldProductIds), count($oldQuantities), count($oldCostPrices), 1);
    @endphp

    <div class="card">
        <div class="card-header">แก้ไขข้อมูลซื้อเข้า</div>
        <div class="card-body">
            <form id="purchase-update-form" class="purchase-form"
                action="{{ route('purchases.update', $purchase) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="row">
                    <div class="col-md-4">
                        <label>ผู้จำหน่าย</label>
                        <select name="supplier_id" class="form-control">
                            <option value="">-- เลือกผู้จำหน่าย --</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}"
                                    @selected((string) old('supplier_id', $purchase->supplier_id) === (string) $supplier->id)>
                                    {{ $supplier->name }}{{ $supplier->active ? '' : ' (ปิดใช้งาน)' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label>วันที่</label>
                        <input type="date" name="purchase_date" class="form-control"
                            value="{{ old('purchase_date', $purchase->purchase_date) }}">
                    </div>
                </div>

                <hr>

                <button type="button" id="addRow" class="btn btn-primary btn-sm mb-2">+ เพิ่มรายการ</button>

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
                                                {{ $product->name }}{{ $product->active ? '' : ' (ปิดใช้งาน)' }}
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

                <button type="submit" class="btn btn-success">บันทึกการแก้ไข</button>
                <a href="{{ route('purchases.show', $purchase) }}" class="btn btn-secondary">ยกเลิก</a>
            </form>

            <form action="{{ route('purchases.destroy', $purchase) }}" method="POST"
                class="purchase-delete-form d-inline-block mt-2"
                data-confirm-message="ยืนยันลบรายการรับเข้านี้? สต๊อกจะถูกปรับออก">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger">ลบรายการรับเข้า</button>
            </form>
        </div>
    </div>
@stop

@section('js')
    <script src="{{ asset('js/modules/purchase.js') }}"></script>
@stop
