@extends('adminlte::page')

@section('title', 'พิมพ์บาร์โค้ด')

@section('content_header')
    <h1>พิมพ์ป้ายราคา / Barcode</h1>
@stop

@section('content')

    <div class="card">

        <div class="card-header">
            เลือกสินค้า
        </div>

        <div class="card-body">

            <div class="row mb-3">

                <div class="col-md-6">
                    <label>สินค้า</label>

                    <select id="product-select" class="form-control">

                        <option value="">-- เลือกสินค้า --</option>

                        @foreach ($products as $product)
                            <option value="{{ $product->id }}" data-name="{{ $product->name }}"
                                data-price="{{ $product->selling_price }}" data-barcode="{{ $product->barcode }}">

                                {{ $product->name }}

                            </option>
                        @endforeach

                    </select>
                </div>

                <div class="col-md-2">
                    <label>จำนวนป้าย</label>

                    <input type="number" id="label-count" class="form-control" value="1" min="1">
                </div>

                <div class="col-md-2">
                    <label>&nbsp;</label>

                    <button type="button" id="generate-label" class="btn btn-primary btn-block">

                        สร้างป้าย

                    </button>
                </div>

                <div class="col-md-2">
                    <label>&nbsp;</label>

                    <button type="button" onclick="window.print()" class="btn btn-success btn-block">

                        พิมพ์

                    </button>
                </div>

            </div>

        </div>

    </div>

    <div id="print-area" class="label-grid"></div>

@stop

@section('css')
    <style>
        .label-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .price-label {
            width: 220px;
            height: 130px;
            border: 1px solid #000;
            padding: 6px;
            text-align: center;
            page-break-inside: avoid;
            background: #fff;
        }

        .label-name {
            font-size: 18px;
            font-weight: bold;
            height: 35px;
            overflow: hidden;
        }

        .label-price {
            font-size: 21px;
            font-weight: bold;
            margin-top: 3px;
        }

        .barcode-svg {
            width: 190px;
            height: 55px;
        }

        @media print {

            .main-header,
            .main-sidebar,
            .content-header,
            .card,
            .btn {
                display: none !important;
            }

            .content-wrapper {
                margin-left: 0 !important;
            }

            #print-area {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
            }

            .price-label {
                border: 1px solid #000;
            }
        }
    </style>
@stop

@section('js')
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>

    <script>
        $('#generate-label').on('click', function() {
            let selected = $('#product-select option:selected');

            let name = selected.data('name');
            let price = selected.data('price');
            let barcode = selected.data('barcode');
            let count = parseInt($('#label-count').val()) || 1;

            if (!name) {
                alert('กรุณาเลือกสินค้า');
                return;
            }

            if (!barcode) {
                alert('สินค้านี้ยังไม่มี Barcode');
                return;
            }

            let html = '';

            for (let i = 0; i < count; i++) {
                html += `
                <div class="price-label">
                    <div class="label-name">${name}</div>
                    <div class="label-price">${parseFloat(price).toFixed(2)} บาท</div>

                    <svg class="barcode-svg"
                        jsbarcode-format="CODE128"
                        jsbarcode-value="${barcode}"
                        jsbarcode-textmargin="0"
                        jsbarcode-fontoptions="bold">
                    </svg>
                </div>
            `;
            }

            $('#print-area').html(html);

            JsBarcode(".barcode-svg", barcode, {
                width: 2,
                height: 50,
                displayValue: true
            });
        });
    </script>
@stop
