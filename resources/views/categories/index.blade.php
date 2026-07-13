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

<div class="card">

    <div class="card-header">

        <form method="POST"
              action="{{ route('categories.store') }}">

            @csrf

            <div class="row">

                <div class="col-md-8">

                    <input type="text"
                           name="name"
                           class="form-control"
                           placeholder="ชื่อหมวดสินค้า">

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
                    <th width="250">จัดการ</th>
                </tr>
            </thead>

            <tbody>

                @foreach($categories as $category)

                <tr>

                    <td>{{ $category->id }}</td>

                    <td>{{ $category->name }}</td>

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
