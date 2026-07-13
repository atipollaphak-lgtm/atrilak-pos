@extends('adminlte::page')

@section('title', 'ที่อยู่จัดส่ง')

@section('content_header')
    <h1>
        ที่อยู่จัดส่ง :
        {{ $customer->name }}
    </h1>
@stop

@section('content')

<div class="card">

    <div class="card-body">

        <a
            href="{{ route('customers.delivery-addresses.create', $customer) }}"
            class="btn btn-primary mb-3"
        >
            <i class="fas fa-plus"></i>
            เพิ่มที่อยู่จัดส่ง
        </a>

        @include('customers.delivery-addresses.partials._table')

    </div>

</div>

@stop
