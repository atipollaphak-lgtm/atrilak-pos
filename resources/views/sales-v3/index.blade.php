@extends('adminlte::page')

@section('title', 'POS V3')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sale-v3.css') }}">
@stop

@section('content')
    <div id="pos-v3" class="pos-v3-shell" data-store-url="{{ route('sales.v3.store') }}" data-address-url-template="{{ url('/sales-v3/customers/__CUSTOMER__/delivery-addresses-json') }}">
        @include('sales-v3.partials.customer-bar')

        <div class="pos-v3-workspace">
            <section class="pos-v3-products card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-boxes mr-2"></i>เลือกสินค้า</span>
                    <span class="small">F2 ค้นหา · F8 Barcode</span>
                </div>
                <div class="card-body">
                    @include('sales-v3.partials.product-navigation')
                    @include('sales-v3.partials.product-grid')
                </div>
            </section>

            @include('sales-v3.partials.cart')
        </div>

        @include('sales-v3.partials.quantity-modal')
        @include('sales-v3.partials.edit-item-modal')
        @include('sales-v3.partials.note-modal')
        @include('sales.partials.payment-modal')
    </div>
@stop

@section('js')
    <script src="{{ asset('js/modules/pos-utils.js') }}"></script>
    <script src="{{ asset('js/modules/sale-intent-storage.js') }}"></script>
    <script src="{{ asset('js/modules/pos-payment.js') }}"></script>
    <script src="{{ asset('js/modules/sale-v3.js') }}"></script>
@stop
