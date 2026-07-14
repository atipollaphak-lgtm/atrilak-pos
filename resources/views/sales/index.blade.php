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
            <form action="{{ route('sales.store') }}" method="POST" id="saleForm"
                data-success-url="{{ route('sales.index') }}">

                @csrf
                <input type="hidden" name="idempotency_key" id="sale-idempotency-key">

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

                    <div class="col-md-4">
                        <label>ช่าง</label>
                        <select name="technician_id" class="form-control">
                            <option value="">-- ไม่ระบุช่าง --</option>
                            @foreach ($technicians as $technician)
                                <option value="{{ $technician->id }}">
                                    {{ $technician->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                </div>

                <hr>

                <div class="row pos-layout">

                    {{-- ซ้าย: สินค้า --}}
                    <div class="col-lg-8">

                        <div class="pos-product-panel">

                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label>ค้นหาสินค้า / บาร์โค้ด</label>
                                    <input type="text" id="productSearch" class="form-control"
                                        style="
                                            font-size:20px;
                                            height:55px;
                                            font-weight:bold;
                                        ">
                                </div>
                            </div>

                            <div class="quick-pos-box mb-3">

                                <div class="mb-2">
                                    <strong>หมวดสินค้า</strong>
                                </div>

                                <div class="category-tabs mb-3">

                                    <button type="button" class="btn btn-dark btn-sm category-filter active"
                                        data-category="all">
                                        ทั้งหมด
                                    </button>

                                    @foreach ($products->pluck('category')->filter()->unique('id') as $category)
                                        <button type="button" class="btn btn-outline-dark btn-sm category-filter"
                                            data-category="{{ $category->id }}">
                                            {{ $category->name }}
                                        </button>
                                    @endforeach

                                </div>

                                <div class="row quick-product-list">

                                    @foreach ($products as $product)
                                        @php
                                            $saleUnit =
                                                $product->productUnits->firstWhere('is_sale_unit', true) ??
                                                $product->productUnits->first();
                                        @endphp
                                        <div class="col-md-3 col-sm-4 col-6 mb-2 quick-product-item"
                                            data-category="{{ $product->category_id }}">

                                            <button type="button"
                                                class="btn btn-outline-primary btn-block quick-product-btn"
                                                data-id="{{ $product->id }}" data-name="{{ $product->name }}"
                                                data-price="{{ $product->selling_price }}"
                                                data-cost="{{ $product->cost_price }}"
                                                data-stock="{{ $product->stock_qty }}"
                                                data-tiers='@json(optional($saleUnit)->priceTiers ?? [])'
                                                data-barcode="{{ $product->barcode }}">

                                                <div class="quick-product-name">
                                                    {{ $product->name }}
                                                </div>

                                                <div class="quick-product-price">
                                                    {{ number_format($product->selling_price, 2) }} บาท
                                                </div>

                                                @if ($product->stock_qty <= 0)
                                                    <span class="badge badge-danger">
                                                        หมด
                                                    </span>
                                                @elseif($product->stock_qty <= 5)
                                                    <span class="badge badge-warning">
                                                        เหลือน้อย
                                                    </span>
                                                @else
                                                    <span class="badge badge-success">
                                                        {{ number_format($product->stock_qty, 0) }}
                                                    </span>
                                                @endif

                                            </button>

                                        </div>
                                    @endforeach

                                </div>

                            </div>

                        </div>

                    </div>

                    {{-- ขวา: ตะกร้า --}}
                    <div class="col-lg-4">

                        <div class="card cart-panel">
                            <button type="button" id="addRow" class="btn btn-primary btn-sm mb-2 d-none">
                                + เพิ่มสินค้า
                            </button>
                            <div class="card-header bg-dark text-white">
                                ตะกร้าขาย
                            </div>

                            <div class="card-body">



                                <table class="table table-bordered table-sm">

                                    <thead>
                                        <tr>
                                            <th width="35%">สินค้า</th>
                                            <th width="12%">คงเหลือ</th>
                                            <th width="13%">จำนวน</th>
                                            <th width="17%">ราคา</th>
                                            <th width="200">
                                                เรทที่ใช้
                                            </th>
                                            <th width="18%">รวม</th>
                                            <th width="5%">ลบ</th>

                                        </tr>
                                    </thead>

                                    <tbody id="sale-items">

                                        <tr>
                                            <td>
                                                <select name="product_id[]" class="form-control product-select">

                                                    <option value="" data-price="0" data-stock="0" data-barcode=""
                                                        data-name="">
                                                        -- เลือกสินค้า --
                                                    </option>

                                                    @foreach ($products as $product)
                                                        @php
                                                            $saleUnit =
                                                                $product->productUnits->firstWhere(
                                                                    'is_sale_unit',
                                                                    true,
                                                                ) ?? $product->productUnits->first();
                                                        @endphp
                                                        <option value="{{ $product->id }}"
                                                            data-price="{{ $product->selling_price }}"
                                                            data-cost="{{ $product->cost_price }}"
                                                            data-stock="{{ $product->stock_qty }}"
                                                            data-tiers='@json(optional($saleUnit)->priceTiers ?? [])'
                                                            data-barcode="{{ $product->barcode }}"
                                                            data-name="{{ $product->name }}">
                                                            {{ $product->name }}
                                                        </option>
                                                    @endforeach

                                                </select>
                                            </td>

                                            <td class="stock-display text-center">
                                                -
                                            </td>

                                            <td>
                                                <input type="number" name="qty[]" class="form-control qty">

                                                <div class="stock-warning-text d-none">
                                                    สต๊อกไม่พอ
                                                </div>
                                            </td>

                                            <td>
                                                <input type="number" step="0.01" name="selling_price[]"
                                                    class="form-control price">
                                            </td>
                                            <td>
                                                <small class="text-success tier-info">
                                                </small>
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

                                <div class="row mt-3">
                                    <div class="col-md-4">
                                        <label>ค่าขนส่ง</label>
                                        <input type="number" name="delivery_fee" id="delivery_fee" class="form-control"
                                            value="0" min="0" step="0.01">
                                    </div>

                                    <div class="col-md-4">
                                        <label>ส่วนลด</label>
                                        <input type="number" name="discount" id="discount" class="form-control"
                                            value="0" min="0" step="0.01">
                                    </div>

                                    <div class="col-md-4">
                                        <label>ยอดสุทธิ</label>

                                        <input type="text" id="net_total"
                                            class="form-control text-center font-weight-bold" value="0.00" readonly
                                            style="
                                                font-size:28px;
                                                height:60px;
                                                background:#fff8dc;
                                                color:#d9534f;
                                            ">
                                    </div>
                                </div>

                                <div class="text-end mt-3">

                                    <h4>
                                        ยอดขาย :
                                        <span id="grand-total">0.00</span>
                                        บาท
                                    </h4>

                                    <h5>
                                        ต้นทุน :
                                        <span id="total-cost">0.00</span>
                                        บาท
                                    </h5>
                                    @role('owner')
                                        <div class="card mt-3">

                                            <div class="card-header bg-success text-white">
                                                สรุปกำไร
                                            </div>

                                            <div class="card-body text-end">

                                                <h4 id="profit-box" class="text-success">
                                                    กำไร :
                                                    <span id="gross-profit">0.00</span>
                                                    บาท
                                                </h4>

                                            </div>
                                        </div>
                                    @endrole
                                    <h5>
                                        Margin :
                                        <span id="profit-percent">0.00</span> %
                                    </h5>

                                </div>

                                <button type="submit" id="btn-submit-sale-v1"
                                    class="btn btn-success btn-lg btn-block shadow mt-3">
                                    💰 บันทึกการขาย (F4)
                                </button>

                            </div>

                        </div>

                    </div>

                </div>

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
                        input.dataset.basePrice = '';
                    });

                clone.querySelector('.product-select').selectedIndex = 0;

                clone.querySelector('.stock-display').innerHTML =
                    '-';

                clone.querySelector('.line-total').innerText =
                    '0.00';

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

                        e.target.closest('tr').remove();

                    } else {

                        let row = e.target.closest('tr');

                        row.querySelector('.product-select').selectedIndex = 0;
                        row.querySelector('.stock-display').innerHTML = '-';
                        row.querySelector('.qty').value = '';
                        row.querySelector('.price').value = '';
                        row.querySelector('.line-total').innerText = '0.00';
                    }

                    calculateTotals();

                }

            }
        );
    </script>

    <script>
        function calculateTotals() {

            let grandTotal = 0;
            let totalCost = 0;

            document.querySelectorAll(
                '#sale-items tr'
            ).forEach(function(row) {

                let qty = parseFloat(
                    row.querySelector('.qty')?.value || 0
                );

                let priceInput = row.querySelector('.price');

                if (!priceInput.dataset.basePrice) {
                    priceInput.dataset.basePrice = priceInput.value || 0;
                }

                let basePrice = parseFloat(priceInput.dataset.basePrice || 0);

                let price = basePrice;

                let select = row.querySelector('.product-select');

                let tiers = [];

                try {
                    tiers = JSON.parse(
                        select.options[select.selectedIndex]?.dataset.tiers || '[]'
                    );
                } catch (e) {
                    tiers = [];
                }

                let matchedTier = null;

                tiers.forEach(function(tier) {

                    let minQty = parseFloat(tier.min_qty || 0);

                    if (qty >= minQty) {
                        if (
                            matchedTier === null ||
                            minQty > parseFloat(matchedTier.min_qty || 0)
                        ) {
                            matchedTier = tier;
                        }
                    }

                });

                if (matchedTier !== null) {

                    let tierInfo =
                        row.querySelector('.tier-info');

                    if (
                        matchedTier.fixed_price !== null &&
                        matchedTier.fixed_price !== ""
                    ) {

                        tierInfo.innerHTML =
                            matchedTier.min_qty +
                            ' ชิ้น<br>ราคา ' +
                            matchedTier.fixed_price;

                    } else {

                        tierInfo.innerHTML =
                            matchedTier.min_qty +
                            ' ชิ้น<br>-' +
                            matchedTier.discount_percent +
                            '%';

                    }


                    if (
                        matchedTier.fixed_price !== null &&
                        matchedTier.fixed_price !== ""
                    ) {

                        price = parseFloat(matchedTier.fixed_price);

                    } else if (
                        matchedTier.discount_percent !== null &&
                        matchedTier.discount_percent !== ""
                    ) {

                        let percent = parseFloat(matchedTier.discount_percent);

                        price = basePrice - (basePrice * percent / 100);

                    }

                    if (price < 0) {
                        price = 0;
                    }
                }

                if (matchedTier !== null) {
                    price = Math.round(price);
                }

                priceInput.value = price.toFixed(2);


                let cost = parseFloat(
                    select.options[
                        select.selectedIndex
                    ]?.dataset.cost || 0
                );

                let lineTotal = qty * price;

                let lineCost = qty * cost;

                row.querySelector(
                        '.line-total'
                    ).innerText =
                    lineTotal.toFixed(2);

                grandTotal += lineTotal;

                totalCost += lineCost;

            });

            let grossProfit =
                grandTotal - totalCost;

            let profitPercent = 0;

            if (grandTotal > 0) {

                profitPercent =
                    (grossProfit / grandTotal) * 100;

            }

            document.getElementById(
                    'grand-total'
                ).innerText =
                grandTotal.toFixed(2);

            document.getElementById(
                    'total-cost'
                ).innerText =
                totalCost.toFixed(2);

            document.getElementById(
                    'gross-profit'
                ).innerText =
                grossProfit.toFixed(2);

            document.getElementById(
                    'profit-percent'
                ).innerText =
                profitPercent.toFixed(2);

            let profitBox =
                document.getElementById(
                    'profit-box'
                );

            if (grossProfit < 0) {

                profitBox.classList.remove(
                    'text-success'
                );

                profitBox.classList.add(
                    'text-danger'
                );

            } else {

                profitBox.classList.remove(
                    'text-danger'
                );

                profitBox.classList.add(
                    'text-success'
                );

            }
            let deliveryFee = parseFloat(
                document.getElementById('delivery_fee')?.value || 0
            );

            let discount = parseFloat(
                document.getElementById('discount')?.value || 0
            );

            let netTotal =
                grandTotal +
                deliveryFee -
                discount;

            document.getElementById(
                'net_total'
            ).value = netTotal.toFixed(2);
        }

        document.addEventListener(
            'input',
            function(e) {

                if (
                    e.target.classList.contains('qty') ||
                    e.target.classList.contains('price') ||
                    e.target.id === 'delivery_fee' ||
                    e.target.id === 'discount'
                ) {

                    calculateTotals();

                }

            }
        );
    </script>

    <script>
        window.addEventListener('load', function() {

            document.querySelectorAll(
                '.product-select'
            ).forEach(function(select) {

                let price =
                    select.options[
                        select.selectedIndex
                    ].dataset.price || 0;

                let row = select.closest('tr');
                let priceInput = row.querySelector('.price');

                priceInput.value = price;
                priceInput.dataset.basePrice = price;

            });

            calculateTotals();

            document.querySelectorAll('.quick-product-btn')
                .forEach(function(btn) {

                    let stock = parseFloat(
                        btn.dataset.stock || 0
                    );

                    if (stock <= 0) {

                        btn.classList.remove(
                            'btn-outline-primary'
                        );

                        btn.classList.add(
                            'btn-danger'
                        );

                    } else if (stock <= 5) {

                        btn.classList.remove(
                            'btn-outline-primary'
                        );

                        btn.classList.add(
                            'btn-warning'
                        );

                    }

                });
        });
    </script>
    <script>
        function updateStockDisplay(select) {
            let stock =
                select.options[
                    select.selectedIndex
                ].dataset.stock || 0;

            let row = select.closest('tr');

            row.querySelector('.stock-display')
                .innerHTML = stock;
        }

        document.addEventListener(
            'change',
            function(e) {

                if (
                    e.target.classList.contains(
                        'product-select'
                    )
                ) {

                    updateStockDisplay(
                        e.target
                    );

                    let price =
                        e.target.options[
                            e.target.selectedIndex
                        ].dataset.price || 0;

                    let row =
                        e.target.closest('tr');

                    let priceInput = row.querySelector('.price');

                    priceInput.value = price;
                    priceInput.dataset.basePrice = price;

                    calculateTotals();

                }

            }
        );

        window.addEventListener(
            'load',
            function() {

                document
                    .querySelectorAll(
                        '.product-select'
                    )
                    .forEach(function(select) {

                        updateStockDisplay(
                            select
                        );

                    });

            }
        );
    </script>
    <script>
        document
            .getElementById('productSearch')
            .addEventListener('keydown', function(e) {

                if (e.key !== 'Enter') {
                    return;
                }

                e.preventDefault();

                let keyword = this.value.trim().toLowerCase();

                if (keyword === '') {
                    return;
                }

                let rows = document.querySelectorAll('#sale-items tr');

                let currentRow = rows[rows.length - 1];

                let select = currentRow.querySelector('.product-select');

                let matchedOption = null;

                Array.from(select.options).forEach(function(option) {

                    let name = (option.dataset.name || '').toLowerCase();

                    let barcode = (option.dataset.barcode || '').toLowerCase();

                    if (
                        name.includes(keyword) ||
                        barcode.includes(keyword)
                    ) {
                        if (matchedOption === null) {
                            matchedOption = option;
                        }
                    }

                });

                if (matchedOption === null) {
                    alert('ไม่พบสินค้า');
                    return;
                }

                let productId = matchedOption.value;

                let existingRow = null;

                document.querySelectorAll('#sale-items tr').forEach(function(row) {

                    let rowSelect = row.querySelector('.product-select');

                    let rowQty = row.querySelector('.qty');

                    if (
                        rowSelect.value == productId &&
                        rowQty.value !== ''
                    ) {
                        existingRow = row;
                    }

                });

                if (existingRow !== null) {

                    let qtyInput = existingRow.querySelector('.qty');

                    let currentQty = parseFloat(qtyInput.value || 0);

                    qtyInput.value = currentQty + 1;

                    checkStock(existingRow);

                    calculateTotals();

                    document.getElementById('productSearch').value = '';

                    qtyInput.focus();
                    qtyInput.select();

                    return;
                }

                if (select.value !== '' || currentRow.querySelector('.qty').value !== '') {

                    document.getElementById('addRow').click();

                    rows = document.querySelectorAll('#sale-items tr');

                    currentRow = rows[rows.length - 1];

                    select = currentRow.querySelector('.product-select');
                }

                select.value = matchedOption.value;

                let price = matchedOption.dataset.price || 0;

                let row = select.closest('tr');

                let priceInput = row.querySelector('.price');

                priceInput.value = price;
                priceInput.dataset.basePrice = price;

                let stock = matchedOption.dataset.stock || 0;

                row.querySelector('.stock-display').innerHTML = stock;

                let qtyInput = row.querySelector('.qty');

                qtyInput.value = 1;

                checkStock(row);

                calculateTotals();

                this.value = '';

                setTimeout(function() {
                    qtyInput.focus();
                    qtyInput.select();
                }, 50);
            });
    </script>
    <script>
        document.addEventListener('keydown', function(e) {

            if (
                e.key === 'Enter' &&
                e.target.classList.contains('qty')
            ) {

                e.preventDefault();

                let qty = parseFloat(e.target.value || 0);

                if (qty <= 0) {
                    alert('กรุณาใส่จำนวนสินค้า');
                    return;
                }

                calculateTotals();

                document.getElementById('productSearch').focus();

            }

        });
    </script>
    <script>
        document.addEventListener('keydown', function(e) {

            if (
                e.key === 'Enter' &&
                e.target.classList.contains('price')
            ) {
                e.preventDefault();

                calculateTotals();

                document.getElementById('addRow').click();

                document.getElementById('productSearch').focus();
            }

        });
    </script>
    <style>
        .stock-error {
            border: 2px solid red !important;
            background-color: #ffecec;
        }

        .quick-product-btn {
            height: 75px;
            white-space: normal;
            font-size: 13px;
        }

        .stock-warning-text {
            color: red;
            font-size: 13px;
            margin-top: 4px;
        }

        .quick-pos-box {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 12px;
            background: #f8f9fa;
        }

        .category-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .quick-product-btn {
            height: 110px;
            width: 100%;
            white-space: normal;
            text-align: center;
            padding: 8px;
        }

        .quick-product-name {
            font-weight: bold;
            font-size: 14px;
            line-height: 1.2;
            height: 38px;
            overflow: hidden;
        }

        .quick-product-price {
            font-size: 13px;
            margin-top: 5px;
        }

        .quick-product-stock {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
        }

        .pos-layout {
            align-items: flex-start;
        }

        .cart-panel {
            position: sticky;
            top: 10px;
        }

        .pos-product-panel {
            min-height: 500px;
        }

        .quick-product-btn {
            height: 130px;
        }

        .quick-product-btn:hover {

            transform: scale(1.03);

            transition: .15s;

        }
    </style>
    <script>
        function checkStock(row) {

            let stockText = row.querySelector('.stock-display').innerText;

            let stock = parseFloat(stockText || 0);

            let qtyInput = row.querySelector('.qty');

            let qty = parseFloat(qtyInput.value || 0);

            let warning = row.querySelector('.stock-warning-text');

            if (qty > stock) {
                qtyInput.classList.add('stock-error');
                warning.classList.remove('d-none');
                return false;
            } else {
                qtyInput.classList.remove('stock-error');
                warning.classList.add('d-none');
                return true;
            }
        }

        document.addEventListener('input', function(e) {

            if (e.target.classList.contains('qty')) {

                let row = e.target.closest('tr');

                checkStock(row);
            }

        });
    </script>
    <script>
        document.getElementById('saleForm').addEventListener('submit', function(e) {

            let hasItem = false;

            document.querySelectorAll('#sale-items tr').forEach(function(row) {
                let productId = row.querySelector('.product-select')?.value;
                let qty = parseFloat(row.querySelector('.qty')?.value || 0);
                let price = parseFloat(row.querySelector('.price')?.value || 0);

                if (productId && qty > 0 && price > 0) {
                    hasItem = true;
                }
            });

            if (!hasItem) {
                e.preventDefault();
                alert('กรุณาเลือกสินค้าอย่างน้อย 1 รายการก่อนบันทึกการขาย');
                document.getElementById('productSearch').focus();
                return;
            }

            let valid = true;

            document.querySelectorAll('#sale-items tr').forEach(function(row) {

                let productId = row.querySelector('.product-select').value;

                let qty = parseFloat(row.querySelector('.qty').value || 0);

                if (productId && qty > 0) {
                    if (!checkStock(row)) {
                        valid = false;
                    }
                }

            });

            if (!valid) {
                e.preventDefault();
                alert('มีสินค้าบางรายการสต๊อกไม่เพียงพอ');
            }

        });
    </script>

    <script>
        document.addEventListener('keydown', function(e) {

            if (e.key === 'F4') {

                e.preventDefault();

                document
                    .getElementById('saleForm')
                    .requestSubmit();
            }

            if (e.key === 'Escape') {

                e.preventDefault();

                document
                    .getElementById('productSearch')
                    .value = '';

                document
                    .getElementById('productSearch')
                    .focus();
            }

        });
    </script>

    <script>
        document.addEventListener('click', function(e) {

            if (!e.target.closest('.quick-product-btn')) {
                return;
            }

            let button = e.target.closest('.quick-product-btn');

            let productId = button.dataset.id;

            let existingRow = null;

            document.querySelectorAll('#sale-items tr').forEach(function(row) {

                let select = row.querySelector('.product-select');
                let qtyInput = row.querySelector('.qty');

                if (
                    select.value == productId &&
                    qtyInput.value !== ''
                ) {
                    existingRow = row;
                }

            });

            if (existingRow) {

                let qtyInput = existingRow.querySelector('.qty');
                let currentQty = parseFloat(qtyInput.value || 0);

                qtyInput.value = currentQty + 1;

                checkStock(existingRow);
                calculateTotals();

                return;
            }

            let rows = document.querySelectorAll('#sale-items tr');
            let row = rows[rows.length - 1];

            let select = row.querySelector('.product-select');
            let qtyInput = row.querySelector('.qty');
            let priceInput = row.querySelector('.price');

            if (select.value !== '' || qtyInput.value !== '') {
                document.getElementById('addRow').click();

                rows = document.querySelectorAll('#sale-items tr');
                row = rows[rows.length - 1];

                select = row.querySelector('.product-select');
                qtyInput = row.querySelector('.qty');
                priceInput = row.querySelector('.price');
            }

            select.value = productId;

            priceInput.value = button.dataset.price;
            priceInput.dataset.basePrice = button.dataset.price;

            row.querySelector('.stock-display').innerHTML = button.dataset.stock;

            qtyInput.value = 1;

            checkStock(row);
            calculateTotals();

        });
    </script>
    <script>
        document.addEventListener('click', function(e) {

            if (!e.target.classList.contains('category-filter')) {
                return;
            }

            let button = e.target;

            document.querySelectorAll('.category-filter').forEach(function(btn) {
                btn.classList.remove('active', 'btn-dark');
                btn.classList.add('btn-outline-dark');
            });

            button.classList.add('active', 'btn-dark');
            button.classList.remove('btn-outline-dark');

            let categoryId = button.dataset.category;

            document.querySelectorAll('.quick-product-item').forEach(function(item) {

                if (
                    categoryId === 'all' ||
                    item.dataset.category === categoryId
                ) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }

            });

        });
    </script>


    @if (session('print_sale_id'))
        <script>
            window.open(
                "{{ route('sales.invoice', session('print_sale_id')) }}",
                "_blank"
            );
        </script>
    @endif

    <script src="{{ asset('js/modules/pos-v1-submit.js') }}"></script>
@stop
