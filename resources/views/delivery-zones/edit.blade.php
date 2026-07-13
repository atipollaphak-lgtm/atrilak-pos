@extends('adminlte::page')

@section('title', 'แก้ไขโซนจัดส่ง')

@section('content_header')
    <h1>แก้ไขโซนจัดส่ง</h1>
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
                <label>ค่าส่งพื้นฐาน</label>

                <input
                    type="number"
                    step="0.01"
                    min="0"
                    name="base_delivery_fee"
                    class="form-control"
                    value="{{ old('base_delivery_fee', $deliveryZone->base_delivery_fee) }}"
                    required
                >
            </div>

            <div class="form-group mb-3">
                <label>ส่งฟรีเมื่อยอดถึง</label>

                <input
                    type="number"
                    step="0.01"
                    min="0"
                    name="free_delivery_min_amount"
                    class="form-control"
                    value="{{ old('free_delivery_min_amount', $deliveryZone->free_delivery_min_amount) }}"
                >

                <small class="text-muted">
                    เว้นว่าง = ไม่มีโปรส่งฟรี
                </small>
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
                    ใช้ตรวจสอบกำไรของบิลก่อนยืนยันการขาย
                </small>
            </div>

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
