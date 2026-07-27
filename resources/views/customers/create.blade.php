@extends('adminlte::page')
@section('title', 'เพิ่มลูกค้า')
@section('content_header')<h1>เพิ่มลูกค้า</h1>@stop
@section('content')
    <form action="{{ route('customers.store') }}" method="POST" data-customer-form>
        @csrf
        @include('customers._form', ['isEdit' => false])
    </form>
    <script src="{{ asset('js/modules/customer-form.js') }}"></script>
@stop
