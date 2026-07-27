@extends('adminlte::page')
@section('title', 'แก้ไขลูกค้า')
@section('content_header')<h1>แก้ไขลูกค้า</h1>@stop
@section('content')
    <form action="{{ route('customers.update', $customer) }}" method="POST" data-customer-form>
        @csrf @method('PUT')
        @include('customers._form', ['isEdit' => true])
    </form>
    <script src="{{ asset('js/modules/customer-form.js') }}"></script>
@stop
