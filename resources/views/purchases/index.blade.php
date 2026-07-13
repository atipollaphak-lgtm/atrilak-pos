@extends('adminlte::page')

@section('title', 'ซื้อเข้า')

@section('content_header')

    <h1>ซื้อเข้า</h1>
@stop

@section('content')

    <div class="card">


        <div class="card-header">
            บันทึกซื้อเข้า
        </div>

        <div class="card-body">
            <form action="{{ route('purchases.store') }}" method="POST">
                @csrf
                <div class="row">

                    <div class="col-md-4">
                        <label>ผู้จำหน่าย</label>

                        <select name="supplier_id" class="form-control">

                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}">
                                    {{ $supplier->name }}
                                </option>
                            @endforeach

                        </select>
                    </div>

                    <div class="col-md-4">
                        <label>วันที่</label>

                        <input type="date" name="purchase_date" class="form-control" value="{{ date('Y-m-d') }}">
                    </div>

                </div>

                <hr>
                <div class="mb-2">
                    <button type="button" id="addRow" class="btn btn-primary btn-sm">
                        + เพิ่มรายการ
                    </button>
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

                        <tr>

                            <td>
                                <select name="product_id[]" class="form-control">

                                    @foreach ($products as $product)
                                        <option value="{{ $product->id }}">
                                            {{ $product->name }}
                                        </option>
                                    @endforeach

                                </select>
                            </td>

                            <td>
                                <input type="number" name="qty[]" class="form-control qty" value="1">
                            </td>

                            <td>
                                <input type="number" step="0.01" name="cost_price[]" class="form-control cost-price">
                            </td>

                            <td class="line-total">
                                0.00
                            </td>

                            <td>
                                <button type="button" class="btn btn-danger btn-sm remove-row">
                                    ลบ
                                </button>
                            </td>

                        </tr>

                    </tbody>

                </table>
                <div class="text-end mb-3">
                    <h4>
                        ยอดรวมทั้งบิล :
                        <span id="grand-total">
                            0.00
                        </span>
                        บาท
                    </h4>
                </div>
                <button type="submit" class="btn btn-success">
                    บันทึกซื้อเข้า
                </button>

            </form>

        </div>


    </div>

    <div class="card mt-3">


        <div class="card-header">
            รายการซื้อเข้าล่าสุด
        </div>

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

                                <a href="{{ route('purchases.show', $purchase) }}" class="btn btn-info btn-sm">

                                    ดูรายละเอียด

                                </a>
                                <a href="{{ route('purchases.edit', $purchase) }}" class="btn btn-warning btn-sm">
                                    แก้ไข
                                </a>

                            </td>
                        </tr>
                    @endforeach

                </tbody>

            </table>

        </div>


    </div>

    <script>
        document
            .getElementById('addRow')
            .addEventListener('click', function() {

                let row = document.querySelector('#purchase-items tr');

                let clone = row.cloneNode(true);

                clone.querySelectorAll('input').forEach(input => {
                    input.value = '';
                });

                document
                    .getElementById('purchase-items')
                    .appendChild(clone);

                calculateLineTotals();

            });
        document.addEventListener('click', function(e) {

            if (e.target.classList.contains('remove-row')) {

                let rows = document.querySelectorAll(
                    '#purchase-items tr'
                );

                if (rows.length > 1) {
                    e.target.closest('tr').remove();

                    calculateLineTotals();
                }

            }

        });
    </script>
    <script>
        function calculateLineTotals() {

            let grandTotal = 0;

            document.querySelectorAll(
                '#purchase-items tr'
            ).forEach(function(row) {

                let qty = parseFloat(
                    row.querySelector('.qty')?.value || 0
                );

                let cost = parseFloat(
                    row.querySelector('.cost-price')?.value || 0
                );

                let total = qty * cost;

                row.querySelector('.line-total')
                    .innerText = total.toFixed(2);

                grandTotal += total;

            });

            document.getElementById(
                'grand-total'
            ).innerText = grandTotal.toFixed(2);

        }

        document.addEventListener('input', function(e) {

            if (
                e.target.classList.contains('qty') ||
                e.target.classList.contains('cost-price')
            ) {
                calculateLineTotals();
            }

        });
    </script>


@stop
