@extends('adminlte::page')

@section('title', 'แก้ไขที่อยู่จัดส่ง')

@section('content_header')
    <h1>แก้ไขที่อยู่จัดส่ง : {{ $customer->name }}</h1>
@stop

@section('content')

<div class="card">

    <div class="card-body">

        <form
            action="{{ route('customers.delivery-addresses.update', [$customer, $address]) }}"
            method="POST"
        >

            @csrf
            @method('PUT')

            @include('customers.delivery-addresses.partials._form')

        </form>

    </div>

</div>

@stop
