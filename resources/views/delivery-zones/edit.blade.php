@extends('adminlte::page')

@section('title', 'แก้ไขราคาตามโซน')

@section('content_header')
    <h1>แก้ไขราคาตามโซน</h1>
@stop

@section('content')

<div class="card">

    <form action="{{ route('delivery-zones.update', $deliveryZone) }}" method="POST">

        @csrf
        @method('PUT')

        <div class="card-body">

            <div class="form-group mb-3">
                <label>ชื่อโซน</label>

                <input
                    type="text"
                    name="name"
                    class="form-control"
                    value="{{ old('name', $deliveryZone->name) }}"
                    required
                >
            </div>

            <div class="form-group mb-3">
                <label>ลำดับการแสดงผล</label>

                <input
                    type="number"
                    name="sort_order"
                    class="form-control"
                    min="0"
                    value="{{ old('sort_order', $deliveryZone->sort_order) }}"
                    required
                >

                <small class="text-muted">
                    ยิ่งเลขน้อย ยิ่งแสดงก่อน
                </small>
            </div>

            <div class="form-group mb-3">
                <label>เปอร์เซ็นต์บวกราคาสินค้า</label>

                <input
                    type="number"
                    step="0.01"
                    min="0"
                    name="price_markup_percent"
                    class="form-control"
                    value="{{ old('price_markup_percent', $deliveryZone->price_markup_percent ?? 0) }}"
                    required
                >
                <small class="text-muted">ระบบจะบวกราคาสินค้าตามเปอร์เซ็นต์นี้เมื่อเลือกส่งสินค้าไปยังโซนนี้</small>
            </div>

            <div class="form-group mb-3">
                <label>กำไรขั้นต่ำของโซน</label>

                <input
                    type="number"
                    step="0.01"
                    min="0"
                    name="minimum_profit"
                    class="form-control"
                    value="{{ old('minimum_profit', $deliveryZone->minimum_profit ?? 0) }}"
                >

                <small class="text-muted">
                    หากกำไรหลังปรับราคาแล้วยังต่ำกว่าค่านี้ ส่วนต่างจะถูกคิดเป็นค่าส่ง
                </small>
            </div>

            <div class="alert alert-info">ตัวอย่าง: ราคาปกติ 100.00 บาท + {{ old('price_markup_percent', $deliveryZone->price_markup_percent ?? 0) }}% = ราคาตามโซนสำหรับใช้อธิบายการตั้งค่า</div>

            <div class="form-group mb-3">

                <label>
                    <input
                        type="checkbox"
                        name="active"
                        value="1"
                        {{ old('active', $deliveryZone->active) ? 'checked' : '' }}
                    >

                    ใช้งาน
                </label>

            </div>

            <div class="form-group">

                <label>หมายเหตุ</label>

                <textarea
                    name="remark"
                    class="form-control"
                    rows="3"
                >{{ old('remark', $deliveryZone->remark) }}</textarea>

            </div>

        </div>

        <div class="card-footer">

            <button
                class="btn btn-success"
                type="submit">

                บันทึก

            </button>

            <a
                href="{{ route('delivery-zones.index') }}"
                class="btn btn-secondary">

                ยกเลิก

            </a>

        </div>

    </form>

</div>

@stop
