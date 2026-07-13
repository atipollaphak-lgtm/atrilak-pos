@extends('adminlte::page')

@section('title', 'แก้ไขซื้อเข้า')

@section('content_header')
    <h1>แก้ไขซื้อเข้า #{{ $purchase->id }}</h1>
@stop

@section('content')

    <form action="{{ route('purchases.update', $purchase) }}" method="POST">

        @csrf
        @method('PUT')

        <div class="card">
            <div class="card-header">
                แก้ไขข้อมูลซื้อเข้า
            </div>

            <div class="card-body">

                <div class="row">

                    <div class="col-md-4">
                        <label>ผู้จำหน่าย</label>

                        <select name="supplier_id" class="form-control">

                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}"
                                    {{ $purchase->supplier_id == $supplier->id ? 'selected' : '' }}>
                                    {{ $supplier->name }}
                                </option>
                            @endforeach

                        </select>
                    </div>

                    <div class="col-md-4">
                        <label>วันที่</label>

                        <input type="date" name="purchase_date" class="form-control"
                            value="{{ $purchase->purchase_date }}">
                    </div>

                </div>

                <hr>

                <button type="button" id="addRow" class="btn btn-primary btn-sm mb-2">
                    + เพิ่มรายการ
                </button>

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

                        @foreach ($purchase->items as $item)
                            <tr>
                                <td>
                                    <select name="product_id[]" class="form-control">

                                        @foreach ($products as $product)
                                            <option value="{{ $product->id }}"
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
                                    <input type="number" step="0.01" name="cost_price[]" class="form-control cost-price"
                                        value="{{ $item->cost_price }}">
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

                <div class="text-end mb-3">
                    <h4>
                        ยอดรวมทั้งบิล :
                        <span id="grand-total">0.00</span>
                        บาท
                    </h4>
                </div>

                <button type="submit" class="btn btn-success">
                    บันทึกการแก้ไข
                </button>
                <form action="{{ route('purchases.destroy', $purchase) }}" method="POST" style="display:inline-block;"
                    onsubmit="return confirm('ยืนยันลบรายการรับเข้านี้? สต๊อกจะถูกปรับออก');">

                    @csrf
                    @method('DELETE')

                    <button type="submit" class="btn btn-danger">
                        ลบรายการรับเข้า
                    </button>
                </form>
                <a href="{{ route('purchases.show', $purchase) }}" class="btn btn-secondary">
                    ยกเลิก
                </a>

            </div>
        </div>

    </form>

    <script>
        function calculateLineTotals() {

            let grandTotal = 0;

            document.querySelectorAll('#purchase-items tr').forEach(function(row) {

                let qty = parseFloat(row.querySelector('.qty')?.value || 0);
                let cost = parseFloat(row.querySelector('.cost-price')?.value || 0);
                let total = qty * cost;

                row.querySelector('.line-total').innerText = total.toFixed(2);

                grandTotal += total;
            });

            document.getElementById('grand-total').innerText = grandTotal.toFixed(2);
        }

        document.getElementById('addRow').addEventListener('click', function() {

            let row = document.querySelector('#purchase-items tr');
            let clone = row.cloneNode(true);

            clone.querySelectorAll('input').forEach(input => {
                input.value = '';
            });

            clone.querySelector('.line-total').innerText = '0.00';

            document.getElementById('purchase-items').appendChild(clone);

            calculateLineTotals();
        });

        document.addEventListener('click', function(e) {

            if (e.target.classList.contains('remove-row')) {

                let rows = document.querySelectorAll('#purchase-items tr');

                if (rows.length > 1) {
                    e.target.closest('tr').remove();
                    calculateLineTotals();
                }
            }
        });

        document.addEventListener('input', function(e) {

            if (
                e.target.classList.contains('qty') ||
                e.target.classList.contains('cost-price')
            ) {
                calculateLineTotals();
            }
        });

        window.addEventListener('load', function() {
            calculateLineTotals();
        });
    </script>

@stop
