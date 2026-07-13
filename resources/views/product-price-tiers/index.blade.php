@extends('adminlte::page')

@section('title', 'Price Tier Management')

@section('content_header')
    <h1>Price Tier Management</h1>
@stop

@section('content')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                จัดการราคาตามจำนวน
            </h3>
        </div>

        <div class="card-body">

            @include('product-price-tiers.partials.summary-cards')

            @include('product-price-tiers.partials.toolbar')

            @if ($products->isEmpty())
                <div class="alert alert-info mb-0">
                    ยังไม่มีสินค้าในระบบ
                </div>
            @else
                @include('product-price-tiers.partials.table')

                @include('product-price-tiers.partials.modal')
                @include('product-price-tiers.partials.bulk-copy-modal')
            @endif
        </div>
    </div>
@stop

@section('js')
    <script src="{{ asset('js/modules/product-price-tier-management.js') }}"></script>
<script src="{{ asset('js/modules/product-price-tier-bulk-copy.js') }}"></script>
@stop
