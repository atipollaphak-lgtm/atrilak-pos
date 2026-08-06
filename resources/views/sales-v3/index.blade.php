@extends('adminlte::page')

@section('title', 'POS V3')

@section('meta_tags')
    <script>window.localStorage.setItem('AdminLTE:IFrame:Options', '{}');</script>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sale-v3.css') }}?v={{ filemtime(public_path('css/sale-v3.css')) }}">
@stop

@section('content')
    <div id="pos-v3" class="pos-v3-shell" data-store-url="{{ route('sales.v3.store') }}" data-customer-store-url="{{ route('sales.v3.customers.store') }}" data-customer-show-url-template="{{ url('/customers/__CUSTOMER__') }}" data-address-url-template="{{ url('/sales-v3/customers/__CUSTOMER__/delivery-addresses-json') }}" data-hold-store-url="{{ route('sales.v3.hold-bills.store') }}" data-hold-list-url="{{ route('sales.v3.hold-bills.index') }}" data-hold-url-template="{{ url('/sales-v3/hold-bills/__HOLD__') }}" data-document-url-template="{{ url('/sales/__SALE__/invoice-v2') }}" data-sale-date="{{ now()->toDateString() }}">
        @include('sales-v3.partials.final-sidebar')
        @include('sales-v3.partials.customer-bar')

        <div class="pos-v3-workspace">
            <section class="pos-v3-products card">
                <div class="card-body">
                    @include('sales-v3.partials.product-navigation')
                    @include('sales-v3.partials.product-grid')
                </div>
            </section>

            @include('sales-v3.partials.cart')
        </div>

        @include('sales-v3.partials.quantity-modal')
        @include('sales-v3.partials.note-modal')
        @include('sales.partials.payment-modal')
        @include('sales-v3.partials.customer-search-modal')
        @include('sales-v3.partials.hold-bill-modal')
        @include('sales-v3.partials.sale-history-modal')
        @include('sales-v3.partials.final-payment-modal')
        @include('sales-v3.partials.customer-create-modal')
    </div>
@stop

@section('js')
    <script src="{{ asset('js/modules/pos-date.js') }}"></script>
    <script src="{{ asset('js/modules/pos-utils.js') }}"></script>
    <script src="{{ asset('js/modules/sale-intent-storage.js') }}"></script>
    <script src="{{ asset('js/modules/pos-payment.js') }}"></script>
    <script src="{{ asset('js/modules/zone-pricing.js') }}"></script>
    <script src="{{ asset('js/modules/final-pos.js') }}?v={{ filemtime(public_path('js/modules/final-pos.js')) }}"></script>
    <script src="{{ asset('js/modules/sale-v3.js') }}?v={{ filemtime(public_path('js/modules/sale-v3.js')) }}"></script>
@stop
