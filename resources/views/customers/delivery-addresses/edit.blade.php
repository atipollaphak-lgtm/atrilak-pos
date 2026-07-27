@extends('adminlte::page')

@section('title', 'แก้ไขที่อยู่จัดส่ง')

@section('content_header')
    <h1>แก้ไขที่อยู่จัดส่ง : {{ $customer->name }}</h1>
<script src="{{ asset('js/modules/customer-form.js') }}"></script>
@stop

@section('content')

<div class="card">

    <div class="card-body">

        <form
            action="{{ route('customers.delivery-addresses.update', [$customer, $address]) }}"
            method="POST"
            data-customer-form
        >

            @csrf
            @method('PUT')

            @include('customers.delivery-addresses.partials._form')

            <button type="submit" class="btn btn-success">บันทึกการแก้ไข</button>
            <a href="{{ route('customers.show', $customer) }}" class="btn btn-secondary">ยกเลิก</a>

        </form>

    </div>

</div>

@stop
