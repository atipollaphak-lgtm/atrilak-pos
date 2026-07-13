@extends('adminlte::page')

@section('title', 'POS ขายสินค้า')

@section('content_header')
    <h1>POS ขายสินค้า</h1>
@stop

@section('content')
    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    <div class="card">

        <div class="card-header">
            ขายสินค้า
        </div>

        <div class="card-body">
            <form action="{{ route('sales.store') }}" method="POST">

                @csrf

                <div class="row">

                    <div class="col-md-4">

                        <label>ลูกค้า</label>

                        <select name="customer_id" class="form-control">

                            <option value="">
                                ลูกค้าทั่วไป
                            </option>

                            @foreach ($customers as $customer)
                                <option value="{{ $customer->id }}">
                                    {{ $customer->name }}
                                </option>
                            @endforeach

                        </select>

                    </div>

                    <div class="col-md-4">

                        <label>วันที่</label>

                        <input type="date" name="sale_date" class="form-control" value="{{ date('Y-m-d') }}">

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

                        <tr>

                            <td>

                                <select name="product_id[]" class="form-control product-select">

                                    @foreach ($products as $product)
                                        <option value="{{ $product->id }}" data-price="{{ $product->selling_price }}">
                                            {{ $product->name }}
                                        </option>
                                    @endforeach

                                </select>

                            </td>

                            <td>
                                <input type="number" name="qty[]" class="form-control qty">
                            </td>

                            <td>
                                <input type="number" step="0.01" name="selling_price[]" class="form-control price">
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

                <div class="text-end">

                    <h4>

                        ยอดรวม :

                        <span id="grand-total">
                            0.00
                        </span>

                        บาท

                    </h4>

                </div>



                <button class="btn btn-success">
                    บันทึกการขาย
                </button>

            </form>



        </div>

    </div>

    <script>
        document
            .getElementById('addRow')
            .addEventListener('click', function() {

                let row = document.querySelector(
                    '#sale-items tr'
                );

                let clone = row.cloneNode(true);

                clone.querySelectorAll('input')
                    .forEach(input => {
                        input.value = '';
                    });

                document
                    .getElementById('sale-items')
                    .appendChild(clone);

            });

        document.addEventListener(
            'click',
            function(e) {

                if (
                    e.target.classList.contains(
                        'remove-row'
                    )
                ) {

                    let rows = document.querySelectorAll(
                        '#sale-items tr'
                    );

                    if (rows.length > 1) {

                        e.target
                            .closest('tr')
                            .remove();

                    }

                }

            }
        );
    </script>

    <script>
        function calculateTotals() {

            let grandTotal = 0;

            document.querySelectorAll(
                '#sale-items tr'
            ).forEach(function(row) {

                let qty = parseFloat(
                    row.querySelector('.qty')?.value || 0
                );

                let price = parseFloat(
                    row.querySelector('.price')?.value || 0
                );

                let total = qty * price;

                row.querySelector(
                    '.line-total'
                ).innerText = total.toFixed(2);

                grandTotal += total;

            });

            document.getElementById(
                'grand-total'
            ).innerText = grandTotal.toFixed(2);

        }

        document.addEventListener(
            'input',
            function(e) {

                if (
                    e.target.classList.contains('qty') ||
                    e.target.classList.contains('price')
                ) {

                    calculateTotals();

                }

            }
        );
    </script>
    <div class="card mt-3">

        <div class="card-header">
            รายการขายล่าสุด
        </div>

        <div class="card-body">

            <table class="table table-bordered">
                <thead>

                    <tr>
                        <th>ID</th>
                        <th>เลขที่บิล</th>
                        <th>วันที่</th>
                        <th>ลูกค้า</th>
                        <th>ยอดรวม</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($sales as $sale)
                        <tr>
                            <td>{{ $sale->id }}</td>
                            <td>{{ $sale->sale_no }}</td>
                            <td>{{ $sale->sale_date }}</td>
                            <td>{{ $sale->customer->name ?? 'ลูกค้าทั่วไป' }}</td>
                            <td>{{ number_format($sale->total_amount, 2) }}</td>
                            <td>
                                <a href="{{ route('sales.show', $sale->id) }}" class="btn btn-info btn-sm">
                                    ดูบิล
                                </a>


                                <a href="{{ route('sales.edit', $sale->id) }}" class="btn btn-warning btn-sm">
                                    แก้ไข
                                </a>
                                <form action="{{ route('sales.destroy', $sale->id) }}" method="POST"
                                    style="display:inline-block;"
                                    onsubmit="return confirm('ยืนยันลบบิลนี้? สต๊อกจะถูกคืนกลับ');">

                                    @csrf
                                    @method('DELETE')

                                    <button type="submit" class="btn btn-danger btn-sm">
                                        ลบ
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

        </div>

    </div>
    <script>
        document.addEventListener('change', function(e) {

            if (e.target.classList.contains('product-select')) {

                let price =
                    e.target.options[
                        e.target.selectedIndex
                    ].dataset.price || 0;

                let row = e.target.closest('tr');

                row.querySelector('.price').value = price;

                calculateTotals();
            }

        });
        window.addEventListener('load', function() {

            document.querySelectorAll(
                '.product-select'
            ).forEach(function(select) {

                let price =
                    select.options[
                        select.selectedIndex
                    ].dataset.price || 0;

                select
                    .closest('tr')
                    .querySelector('.price')
                    .value = price;

            });

            calculateTotals();

        });
    </script>
@stop
