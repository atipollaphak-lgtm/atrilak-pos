@extends('adminlte::page')

@section('title', 'ตรวจนับสต็อก')

@section('content_header')
    <h1>ตรวจนับ / ปรับสต็อก</h1>
@stop

@section('content')

@if(session('success'))
    <div class="alert alert-success">
        {{ session('success') }}
    </div>
@endif

<div class="card">

    <div class="card-header">
        ปรับสต็อกสินค้า
    </div>

    <div class="card-body">

        <form action="{{ route('stock-counts.store') }}" method="POST">

            @csrf

            <div class="row">

                <div class="col-md-4">

                    <label>สินค้า</label>

                    <select
                        name="product_id"
                        class="form-control"
                        required>

                        <option value="">
                            -- เลือกสินค้า --
                        </option>

                        @foreach($products as $product)

                            <option value="{{ $product->id }}">

                                {{ $product->name }}
                                (คงเหลือ {{ $product->stock_qty }})

                            </option>

                        @endforeach

                    </select>

                </div>

                <div class="col-md-4">

                    <label>จำนวนที่นับได้จริง</label>

                    <input
                        type="number"
                        step="0.01"
                        name="actual_qty"
                        class="form-control"
                        required>

                </div>

                <div class="col-md-4">

                    <label>&nbsp;</label>

                    <button
                        type="submit"
                        class="btn btn-warning d-block">

                        ปรับสต็อก

                    </button>

                </div>

            </div>

        </form>

    </div>

</div>

@stop
