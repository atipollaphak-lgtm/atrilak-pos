@extends('adminlte::page')

@section('title', 'ตั้งค่าร้าน')

@section('content_header')
    <h1>ตั้งค่าร้าน</h1>
@stop

@section('content')

    <div class="card">

        <div class="card-header">
            ข้อมูลร้าน
        </div>

        <div class="card-body">

            @if (session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif

            <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data">

                @csrf

                <div class="form-group">
                    <label>ชื่อร้าน</label>
                    <input type="text" name="store_name" class="form-control" value="{{ $setting->store_name }}">
                </div>

                <div class="form-group">
                    <label>ที่อยู่ร้าน</label>
                    <textarea name="store_address" class="form-control" rows="3">{{ $setting->store_address }}</textarea>
                </div>

                <div class="form-group">
                    <label>เบอร์โทร</label>
                    <input type="text" name="store_phone" class="form-control" value="{{ $setting->store_phone }}">
                </div>

                <div class="form-group">
    <label>เลขผู้เสียภาษี</label>
    <input
        type="text"
        name="tax_number"
        class="form-control"
        value="{{ $setting->tax_number }}">
</div>

<div class="form-group">
    <label>ประเภทสาขา</label>

    <select name="branch_type" class="form-control">

        <option value="head_office"
            {{ ($setting->branch_type ?? 'head_office') == 'head_office' ? 'selected' : '' }}>
            สำนักงานใหญ่
        </option>

        <option value="branch"
            {{ ($setting->branch_type ?? '') == 'branch' ? 'selected' : '' }}>
            สาขา
        </option>

    </select>
</div>

<div class="form-group">
    <label>เลขที่สาขา</label>

    <input
        type="text"
        name="branch_number"
        class="form-control"
        placeholder="เช่น 00001"
        value="{{ $setting->branch_number }}">
</div>

                <div class="form-group">
                    <label>Logo</label>
                    @if($setting->logo_image)
    <div class="mb-3">
        <img
            src="{{ asset('storage/'.$setting->logo_image) }}"
            style="max-height:120px">
    </div>
@endif
                    <input type="file" name="logo_image" class="form-control">

                </div>
                <div class="form-group">
                    <label>QR Payment</label>
                    @if($setting->qr_image)
    <div class="mb-3">
        <img
            src="{{ asset('storage/'.$setting->qr_image) }}"
            style="max-height:180px">
    </div>
@endif
                    <input type="file" name="qr_image" class="form-control">
                </div>

                <button class="btn btn-success">
                    บันทึก
                </button>

            </form>

        </div>

    </div>

@stop
