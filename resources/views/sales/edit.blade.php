@extends('adminlte::page')

@section('title', 'แก้ไขบิลขาย')

@section('content_header')
    <h1>แก้ไขบิลขาย {{ $sale->sale_no }}</h1>
@stop

@section('content')

    <form action="{{ route('sales.update', $sale->id) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="card">
            <div class="card-header">ข้อมูลบิล</div>

            <div class="card-body">

                <div class="row">
                    <div class="col-md-4">
                        <label>ลูกค้า</label>
                        <select name="customer_id" class="form-control">
                            <option value="">ลูกค้าทั่วไป</option>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->id }}"
                                    {{ $sale->customer_id == $customer->id ? 'selected' : '' }}>
                                    {{ $customer->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label>วันที่</label>
                        <input type="date" name="sale_date" class="form-control" value="{{ $sale->sale_date }}">
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
                        @foreach ($sale->items as $item)
                            <tr>
                                <td>
                                    <select name="product_id[]" class="form-control product-select">
                                        @foreach ($products as $product)
                                            <option value="{{ $product->id }}" data-price="{{ $product->selling_price }}"
                                                {{ $item->product_id == $product->id ? 'selected' : '' }}>
                                                {{ $product->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>

                                <td>
                                    <input type="number" name="qty[]" class="form-control qty"
                                        value="{{ $item->qty }}">
                                </td>

                                <td>
                                    <input type="number" step="0.01" name="selling_price[]" class="form-control price"
                                        value="{{ $item->selling_price }}">
                                </td>

                                <td class="line-total">
                                    {{ number_format($item->total, 2) }}
                                </td>

                                <td>
                                    <button type="button" class="btn btn-danger btn-sm remove-row">
                                        ลบ
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="row mt-3">

                    <div class="col-md-4">
                        <label>ค่าขนส่ง</label>

                        <input type="number" name="delivery_fee" id="delivery_fee" class="form-control"
                            value="{{ $sale->delivery_fee ?? 0 }}" step="0.01">
                    </div>

                    <div class="col-md-4">
                        <label>ส่วนลด</label>

                        <input type="number" name="discount" id="discount" class="form-control"
                            value="{{ $sale->discount ?? 0 }}" step="0.01">
                    </div>

                    <div class="col-md-4">
                        <label>ยอดสุทธิ</label>

                        <input type="text" id="net_total" class="form-control" readonly>
                    </div>

                </div>
                <div class="text-end">
                    <h4>
                        ยอดรวม :
                        <span id="grand-total">0.00</span>
                        บาท
                    </h4>
                </div>

                <button class="btn btn-success">
                    บันทึกการแก้ไข
                </button>


                <a href="{{ route('sales.show', $sale->id) }}" class="btn btn-secondary">
                    ยกเลิก
                </a>

            </div>
        </div>
    </form>



    <script>
        function calculateTotals() {
            let grandTotal = 0;

            document.querySelectorAll('#sale-items tr').forEach(function(row) {
                let qty = parseFloat(row.querySelector('.qty')?.value || 0);
                let price = parseFloat(row.querySelector('.price')?.value || 0);
                let total = qty * price;

                row.querySelector('.line-total').innerText = total.toFixed(2);
                grandTotal += total;
            });

            document.getElementById('grand-total').innerText = grandTotal.toFixed(2);
            let deliveryFee =
                parseFloat(
                    document.getElementById('delivery_fee')?.value || 0
                );

            let discount =
                parseFloat(
                    document.getElementById('discount')?.value || 0
                );

            let netTotal =
                grandTotal +
                deliveryFee -
                discount;

            document.getElementById(
                    'net_total'
                ).value =
                netTotal.toFixed(2);
        }

        document.getElementById('addRow').addEventListener('click', function() {
            let row = document.querySelector('#sale-items tr');
            let clone = row.cloneNode(true);

            clone.querySelectorAll('input').forEach(input => {
                input.value = '';
            });

            clone.querySelector('.line-total').innerText = '0.00';

            document.getElementById('sale-items').appendChild(clone);
        });

        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-row')) {
                let rows = document.querySelectorAll('#sale-items tr');

                if (rows.length > 1) {
                    e.target.closest('tr').remove();
                    calculateTotals();
                }
            }
        });

        document.addEventListener('input', function(e) {

            if (
                e.target.classList.contains('qty') ||
                e.target.classList.contains('price') ||
                e.target.id === 'delivery_fee' ||
                e.target.id === 'discount'
            ) {
                calculateTotals();
            }

        });

        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('product-select')) {
                let price = e.target.options[e.target.selectedIndex].dataset.price || 0;

                let row = e.target.closest('tr');

                row.querySelector('.price').value = price;

                calculateTotals();
            }
        });

        window.addEventListener('load', function() {
            calculateTotals();
        });
    </script>

@stop
