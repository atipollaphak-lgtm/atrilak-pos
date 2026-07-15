@extends('adminlte::page')

@section('title', 'POS V2')

@section('content_header')
    <h1>POS V2 (กำลังพัฒนา)</h1>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/pos-v2.css') }}">
@stop

@section('js')

    <script src="{{ asset('js/modules/pos-utils.js') }}"></script>


    <script src="{{ asset('js/modules/pos-cart.js') }}"></script>
    <script src="{{ asset('js/modules/pos-barcode.js') }}"></script>

    <script src="{{ asset('js/modules/pos-search.js') }}"></script>

    <script src="{{ asset('js/modules/sale-intent-storage.js') }}"></script>
    <script src="{{ asset('js/modules/pos-submit.js') }}"></script>

    <script src="{{ asset('js/pos-v2.js') }}"></script>
@stop

@section('content')

    <div class="row">

        {{-- ==========================
        ส่วนบน
    =========================== --}}

        @include('sales.partials.pos-v2-header')

        {{-- ==========================
        ซ้าย
    =========================== --}}

        @include('sales.partials.pos-v2-product-panel')

        {{-- ==========================
        ขวา
    =========================== --}}

        @include('sales.partials.pos-v2-cart-panel')

    </div>

    <!-- Quantity Dialog -->
<div class="modal fade" id="quantityModal" tabindex="-1">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header">

                <h4
                    class="modal-title font-weight-bold"
                    id="quantityProductName"
                >
                    สินค้า
                </h4>

                <button
                    type="button"
                    class="close"
                    data-dismiss="modal"
                >
                    <span>&times;</span>
                </button>

            </div>

            <div class="modal-body">

                <div
                    style="
                        font-size:20px;
                        margin-bottom:15px;
                    "
                >
                    สต๊อกคงเหลือ :

                    <strong id="quantityStock">
                        0
                    </strong>

                </div>

                <label
                    style="
                        font-size:22px;
                        font-weight:bold;
                    "
                >
                    จำนวนที่ลูกค้าจะซื้อ
                </label>

                <input
                    type="number"
                    id="quantityInput"
                    class="form-control form-control-lg"
                    autocomplete="off"
                    style="
                        font-size:34px;
                        height:70px;
                        font-weight:bold;
                    "
                >

                <div
                    id="quantityError"
                    class="text-danger mt-3"
                    style="
                        display:none;
                        font-size:18px;
                        font-weight:bold;
                    "
                ></div>

            </div>

        </div>

    </div>

</div>

<!-- =========================
     Payment Modal
========================= -->

<div
    class="modal fade"
    id="paymentModal"
    tabindex="-1"
>

    <div class="modal-dialog modal-dialog-centered modal-lg">

        <div class="modal-content">

            <div class="modal-header bg-success text-white">

                <h4 class="modal-title">
                    💰 ชำระเงิน
                </h4>

                <button
                    type="button"
                    class="close text-white"
                    data-dismiss="modal"
                >
                    <span>&times;</span>
                </button>

            </div>

            <div class="modal-body">

                <div class="text-center mb-4">

                    <div
                        style="
                            font-size:22px;
                            color:#666;
                        "
                    >
                        ยอดสุทธิ
                    </div>

                    <div
                        id="payment-total"
                        style="
                            font-size:60px;
                            font-weight:bold;
                            color:#dc3545;
                        "
                    >
                        0.00
                    </div>

                </div>

                <label
                    style="
                        font-size:22px;
                        font-weight:bold;
                    "
                >
                    รับเงิน
                </label>

                <input
                    id="payment-received"
                    type="number"
                    class="form-control form-control-lg"
                    style="
                        height:70px;
                        font-size:34px;
                        font-weight:bold;
                    "
                >

                <div
                    class="mt-4 text-center"
                >

                    <div
                        style="
                            font-size:22px;
                            color:#666;
                        "
                    >
                        เงินทอน
                    </div>

                    <div
                        id="payment-change"
                        style="
                            font-size:46px;
                            font-weight:bold;
                            color:#28a745;
                        "
                    >
                        0.00
                    </div>

                </div>

            </div>

            <div class="modal-footer">

                <button
                    class="btn btn-secondary btn-lg"
                    data-dismiss="modal"
                >
                    ยกเลิก
                </button>

                <button
                    id="btn-confirm-payment"
                    class="btn btn-success btn-lg"
                >
                    ยืนยันการขาย
                </button>

            </div>

        </div>

    </div>

</div>

@stop
