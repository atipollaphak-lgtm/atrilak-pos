@extends('adminlte::page')

@section('title', 'หมวดสินค้า')

@section('content_header')
<h1>หมวดสินค้า</h1>
@stop

@section('content')

@if(session('success'))
<div class="alert alert-success">
    {{ session('success') }}
</div>
@endif

@if($errors->any())
<div class="alert alert-danger">
    <ul class="mb-0">
        @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<div class="card">

    <div class="card-header">

        <form method="POST"
              action="{{ route('categories.store') }}">

            @csrf

            <div class="row">

                <div class="col-md-4">

                    <input type="text"
                           name="name"
                           class="form-control"
                           placeholder="ชื่อหมวดสินค้า">

                </div>

                <div class="col-md-2">
                    <input type="text" name="code_prefix" class="form-control" placeholder="ตัวย่อ เช่น CEM" maxlength="20">
                </div>

                <div class="col-md-2">
                    <input type="text" name="barcode_prefix" class="form-control" placeholder="Barcode เช่น 001" maxlength="3">
                </div>

                <div class="col-md-4">

                    <button class="btn btn-primary">
                        เพิ่มหมวดสินค้า
                    </button>

                </div>

            </div>

        </form>

    </div>

    <div class="card-body">

        <table class="table table-bordered">

            <thead>
                <tr>
                    <th>ID</th>
                    <th>ชื่อหมวดสินค้า</th>
                    <th>Code Prefix</th>
                    <th>Barcode Prefix</th>
                    <th width="250">จัดการ</th>
                </tr>
            </thead>

            <tbody>

                @foreach($categories as $category)

                <tr>

                    <td>{{ $category->id }}</td>

                    <td>{{ $category->name }}</td>
                    <td>{{ $category->code_prefix ?: '—' }}</td>
                    <td>{{ $category->barcode_prefix ?: '—' }}</td>

                    <td>

                        <form
                            action="{{ route('categories.update',$category) }}"
                            method="POST"
                            class="d-inline">

                            @csrf
                            @method('PUT')

                            <input type="text"
                                   name="name"
                                   value="{{ $category->name }}">

                            <input type="text" name="code_prefix" value="{{ $category->code_prefix }}" maxlength="20" placeholder="Code Prefix">
                            <input type="text" name="barcode_prefix" value="{{ $category->barcode_prefix }}" maxlength="3" placeholder="Barcode Prefix">

                            <button class="btn btn-warning btn-sm">
                                แก้ไข
                            </button>

                        </form>

                        <form
                            action="{{ route('categories.destroy',$category) }}"
                            method="POST"
                            class="d-inline">

                            @csrf
                            @method('DELETE')

                            <button
                                class="btn btn-danger btn-sm"
                                onclick="return confirm('ลบข้อมูล?')">

                                ลบ

                            </button>

                        </form>

                    </td>

                </tr>

                @endforeach

            </tbody>

        </table>

    </div>

</div>

@stop
