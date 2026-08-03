@extends('adminlte::page')

@section('title', 'รับสินค้าเข้า V2')

@section('content_header')<h1>รับสินค้าเข้า V2</h1>@stop

@section('content')
    @include('partials.flash-messages')
    <div class="alert alert-info">การรับสินค้าจะเพิ่ม Stock และคำนวณ Average Cost เท่านั้น ไม่เปลี่ยน Selling Price หรือ Price Lock อัตโนมัติ</div>
    <form id="receive-stock-form" method="POST" action="{{ route('receivings.preview.store') }}">
        @csrf
        <div class="card">
            <div class="card-header">ข้อมูลเอกสาร</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <label for="source">แหล่งที่มา</label>
                        <select name="source" id="source" class="form-control" required>
                            <option value="supplier">ซื้อจาก Supplier</option>
                            <option value="production">ผลิตเอง</option>
                        </select>
                    </div>
                    <div class="col-md-4" id="supplier-field">
                        <label for="supplier_id">Supplier</label>
                        <select name="supplier_id" id="supplier_id" class="form-control">
                            <option value="">-- เลือก Supplier --</option>
                            @foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-2"><label for="purchase_date">วันที่รับ</label><input id="purchase_date" type="date" name="purchase_date" class="form-control" value="{{ date('Y-m-d') }}" required></div>
                    <div class="col-md-3"><label for="supplier_document_number">เลขที่เอกสาร Supplier</label><input id="supplier_document_number" name="supplier_document_number" class="form-control" maxlength="100"></div>
                </div>
                <div class="mt-2"><label for="remark">หมายเหตุ</label><textarea id="remark" name="remark" class="form-control" rows="2"></textarea></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">รายการสินค้า</div>
            <div class="card-body">
                <div class="input-group mb-3">
                    <input id="product-search" class="form-control" placeholder="ค้นหาชื่อสินค้า / รหัส / Barcode หรือสแกน Barcode">
                    <button type="button" id="search-product" class="btn btn-outline-secondary">ค้นหา</button>
                </div>
                <div id="product-results" class="list-group mb-3" aria-live="polite"></div>
                <div class="table-responsive">
                    <table class="table table-bordered" id="receive-items-table">
                        <thead><tr><th>สินค้า</th><th>หน่วย</th><th>Stock ปัจจุบัน</th><th>Average Cost</th><th>จำนวน</th><th>ต้นทุน/หน่วย</th><th>รวม</th><th></th></tr></thead>
                        <tbody id="receive-items"></tbody>
                    </table>
                </div>
                <div class="text-right h5">ยอดรวม: <span id="receive-total">0.00</span></div>
                <button type="submit" id="preview-button" class="btn btn-primary">ตรวจสอบก่อนยืนยัน</button>
                <a href="{{ route('receivings.index') }}" class="btn btn-secondary">ยกเลิก</a>
            </div>
        </div>
    </form>
@stop

@push('js')
    <script src="{{ asset('js/modules/receive-stock.js') }}"></script>
@endpush
