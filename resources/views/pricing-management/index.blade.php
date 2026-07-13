@extends('adminlte::page')

@section('title', 'Pricing Management')

@section('content_header')
    <h1>Pricing Management</h1>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/pricing-management.css') }}">
@stop

@section('content')


    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            <strong>Pricing UI Dashboard</strong>
        </div>

        <div class="card-body">

            @include('pricing-management.partials.summary-cards')

            @include('pricing-management.partials.category-pricing-panel')

            @include('pricing-management.partials.toolbar')

            @include('pricing-management.partials.table')

            @include('pricing-management.partials.bulk-preview-modal')

        </div>
    </div>
@stop

@section('js')
    <script src="{{ asset('js/modules/pricing-management.js') }}"></script>
@stop
