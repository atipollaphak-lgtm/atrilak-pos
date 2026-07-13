@extends('adminlte::page')

@section('title', 'แก้ไขหน่วย')

@section('content_header')
    <h1>แก้ไขหน่วยสินค้า</h1>
@stop

@section('content')

<div class="card">

    <div class="card-body">

        <form action="{{ route('units.update', $unit) }}"
              method="POST">

            @csrf
            @method('PUT')

            <div class="form-group">

                <label>ชื่อหน่วย</label>

                <input type="text"
                       name="name"
                       class="form-control"
                       value="{{ $unit->name }}">

            </div>

            <div class="form-group">

                <label>สถานะ</label>

                <select name="active"
                        class="form-control">

                    <option value="1"
                        {{ $unit->active ? 'selected' : '' }}>
                        ใช้งาน
                    </option>

                    <option value="0"
                        {{ !$unit->active ? 'selected' : '' }}>
                        ปิดใช้งาน
                    </option>

                </select>

            </div>

            <button class="btn btn-success">
                บันทึก
            </button>

            <a href="{{ route('units.index') }}"
               class="btn btn-secondary">
                กลับ
            </a>

        </form>

    </div>

</div>

@stop
