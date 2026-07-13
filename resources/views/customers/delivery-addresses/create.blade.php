@extends('adminlte::page')

@section('title', 'เพิ่มที่อยู่จัดส่ง')

@section('content_header')
    <h1>เพิ่มที่อยู่จัดส่ง : {{ $customer->name }}</h1>
@stop

@section('content')

    <div class="card">

        <div class="card-body">

            <form action="{{ route('customers.delivery-addresses.store', $customer) }}" method="POST">

    @csrf

    @include('customers.delivery-addresses.partials._form')

    <button type="submit" class="btn btn-success">
        บันทึก
    </button>

    <a href="{{ route('customers.delivery-addresses.index', $customer) }}" class="btn btn-secondary">
        ยกเลิก
    </a>

</form>

        </div>

    </div>

@stop
