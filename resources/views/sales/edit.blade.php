@extends('adminlte::page')

@section('title', 'แก้ไขบิลขาย')

@section('content_header')
    <h1>แก้ไขบิลขาย {{ $sale->sale_no }}</h1>
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
        $defaultSaleItemIds = $sale->items->pluck('id')->all();
        $defaultProductUnitIds = $sale->items->pluck('product_unit_id')->all();
        $defaultProductIds = $sale->items->pluck('product_id')->all();
        $defaultQuantities = $sale->items->pluck('qty')->all();
        $defaultSellingPrices = $sale->items->pluck('selling_price')->all();
        $oldSaleItemIds = is_array(old('sale_item_id')) ? old('sale_item_id') : $defaultSaleItemIds;
        $oldProductUnitIds = is_array(old('product_unit_id')) ? old('product_unit_id') : $defaultProductUnitIds;
        $oldProductIds = is_array(old('product_id')) ? old('product_id') : $defaultProductIds;
        $oldQuantities = is_array(old('qty')) ? old('qty') : $defaultQuantities;
        $oldSellingPrices = is_array(old('selling_price')) ? old('selling_price') : $defaultSellingPrices;
        $rowCount = max(
            count($oldSaleItemIds),
            count($oldProductUnitIds),
            count($oldProductIds),
            count($oldQuantities),
            count($oldSellingPrices),
            1,
        );
        $selectedCustomerId = old('customer_id', $sale->customer_id);
        $customerIsAvailable = $selectedCustomerId === null
            || $selectedCustomerId === ''
            || $customers->contains('id', (int) $selectedCustomerId);
    @endphp

    <form id="sale-edit-form" action="{{ route('sales.update', $sale->id) }}" method="POST">
        @csrf
        @method('PUT')
        <input type="hidden" name="revision" value="{{ old('revision', $sale->revision) }}">

        <div class="card">
            <div class="card-header">ข้อมูลบิล</div>

            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <label>ลูกค้า</label>
                        <select name="customer_id" class="form-control">
                            <option value="">ลูกค้าทั่วไป</option>
                            @unless ($customerIsAvailable)
                                <option value="{{ $selectedCustomerId }}" selected>
                                    ลูกค้าไม่พร้อมใช้งาน #{{ $selectedCustomerId }}
                                </option>
                            @endunless
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->id }}"
                                    @selected((string) $selectedCustomerId === (string) $customer->id)>
                                    {{ $customer->name }}{{ $customer->active ? '' : ' (ปิดใช้งาน)' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label>วันที่</label>
                        <input type="date" name="sale_date" class="form-control"
                            value="{{ old('sale_date', $sale->sale_date) }}">
                    </div>
                </div>

                <hr>

                <button type="button" id="addRow" class="btn btn-primary btn-sm mb-2">
                    + เพิ่มสินค้า
                </button>

                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th width="40%">สินค้า</th>
                            <th>จำนวน</th>
                            <th>ราคา</th>
                            <th>รวม</th>
                            <th width="80">ลบ</th>
                        </tr>
                    </thead>

                    <tbody id="sale-items">
                        @for ($index = 0; $index < $rowCount; $index++)
                            @php
                                $selectedProductId = $oldProductIds[$index] ?? '';
                                $productIsAvailable = $selectedProductId === ''
                                    || $selectedProductId === null
                                    || $products->contains('id', (int) $selectedProductId);
                            @endphp
                            <tr>
                                <td>
                                    <input type="hidden" name="sale_item_id[]" class="sale-item-id"
                                        value="{{ $oldSaleItemIds[$index] ?? '' }}">
                                    <input type="hidden" name="product_unit_id[]" class="product-unit-id"
                                        value="{{ $oldProductUnitIds[$index] ?? '' }}">
                                    <select name="product_id[]" class="form-control product-select">
                                        <option value="">-- เลือกสินค้า --</option>
                                        @unless ($productIsAvailable)
                                            <option class="invalid-historical-option" value="{{ $selectedProductId }}"
                                                selected>
                                                สินค้าไม่พร้อมใช้งาน #{{ $selectedProductId }}
                                            </option>
                                        @endunless
                                        @foreach ($products as $product)
                                            <option value="{{ $product->id }}" data-price="{{ $product->selling_price }}"
                                                data-inactive="{{ $product->active ? '0' : '1' }}"
                                                @disabled(! $product->active && (string) $selectedProductId !== (string) $product->id)
                                                @selected((string) $selectedProductId === (string) $product->id)>
                                                {{ $product->name }}{{ $product->active ? '' : ' (ปิดใช้งาน)' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>

                                <td>
                                    <input type="number" step="0.01" name="qty[]" class="form-control qty"
                                        value="{{ $oldQuantities[$index] ?? '' }}">
                                </td>

                                <td>
                                    <input type="number" step="0.01" name="selling_price[]" class="form-control price"
                                        value="{{ $oldSellingPrices[$index] ?? '' }}">
                                </td>

                                <td class="line-total">0.00</td>

                                <td>
                                    <button type="button" class="btn btn-danger btn-sm remove-row">ลบ</button>
                                </td>
                            </tr>
                        @endfor
                    </tbody>
                </table>

                <div class="row mt-3">
                    <div class="col-md-4">
                        <label>ค่าขนส่ง</label>
                        <input type="number" name="delivery_fee" id="delivery_fee" class="form-control"
                            value="{{ old('delivery_fee', $sale->delivery_fee ?? 0) }}" step="0.01">
                    </div>

                    <div class="col-md-4">
                        <label>ส่วนลด</label>
                        <input type="number" name="discount" id="discount" class="form-control"
                            value="{{ old('discount', $sale->discount ?? 0) }}" step="0.01">
                    </div>

                    <div class="col-md-4">
                        <label>ยอดสุทธิ</label>
                        <input type="text" id="net_total" class="form-control" readonly>
                    </div>
                </div>

                <div class="text-end">
                    <h4>ยอดรวม : <span id="grand-total">0.00</span> บาท</h4>
                </div>

                <button class="btn btn-success">บันทึกการแก้ไข</button>
                <a href="{{ route('sales.show', $sale->id) }}" class="btn btn-secondary">ยกเลิก</a>
            </div>
        </div>
    </form>
@stop

@section('js')
    <script src="{{ asset('js/modules/sale-edit.js') }}"></script>
@stop
