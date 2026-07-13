@extends('adminlte::page')

@section('title', 'เพิ่มกฎค่าช่าง')

@section('content_header')
    <h1>เพิ่มกฎค่าช่าง</h1>
@stop

@section('content')

    <div class="card">
        <div class="card-body">

            <form action="{{ route('technician-commission-rules.store') }}" method="POST">
                @csrf

                <div class="form-group">
                    <label>ชื่อกฎ</label>
                    <input type="text" name="name" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>หมวดสินค้า</label>
                    <select name="category_id" class="form-control">
                        <option value="">-- ไม่ระบุหมวด --</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>สินค้าเฉพาะ</label>
                    <select name="product_id" class="form-control">
                        <option value="">-- ไม่ระบุสินค้า --</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}">{{ $product->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>วิธีคิด</label>
                    <select name="rule_type" class="form-control" required>
                        <option value="percent">เปอร์เซ็นต์จากยอดขาย</option>
                        <option value="amount">บาทต่อหน่วย</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>ค่า</label>
                    <input type="number" step="0.01" name="rule_value" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>หมายเหตุ</label>
                    <textarea name="remark" class="form-control"></textarea>
                </div>

                <div class="form-check mb-3">
                    <input type="checkbox" name="active" value="1" class="form-check-input" checked>
                    <label class="form-check-label">เปิดใช้งาน</label>
                </div>

                <button class="btn btn-success">
                    บันทึก
                </button>

                <a href="{{ route('technician-commission-rules.index') }}" class="btn btn-secondary">
                    กลับ
                </a>
            </form>

        </div>
    </div>

@stop
