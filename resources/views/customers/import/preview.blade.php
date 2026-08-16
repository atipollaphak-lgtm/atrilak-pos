@php($counts = $preview->counts())

@extends('adminlte::page')

@section('title', 'Preview สมาชิกนำเข้า')

@section('content_header')
    <div class="d-flex align-items-center justify-content-between">
        <div>
            <h1 class="mb-1">Preview สมาชิกนำเข้า</h1>
            <p class="text-muted mb-0">{{ $preview->filename }} · {{ $preview->sourceSystem ?: 'ไม่ทราบรูปแบบ' }}</p>
        </div>
        <a href="{{ route('customers.import.index') }}" class="btn btn-light">ยกเลิก</a>
    </div>
@stop

@section('content')
    <form id="customer-import-confirm-form" method="POST" action="{{ route('customers.import.confirm') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $preview->token }}">

    @if ($preview->errors !== [])
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($preview->errors as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row">
        <div class="col-md-3"><div class="small-box bg-success"><div class="inner"><h3>{{ $counts['ready'] }}</h3><p>พร้อมนำเข้า</p></div></div></div>
        <div class="col-md-3"><div class="small-box bg-warning"><div class="inner"><h3>{{ $counts['review_required'] }}</h3><p>ต้องตรวจสอบ</p></div></div></div>
        <div class="col-md-3"><div class="small-box bg-info"><div class="inner"><h3>{{ $counts['duplicate'] }}</h3><p>มีอยู่แล้ว / ซ้ำ</p></div></div></div>
        <div class="col-md-3"><div class="small-box bg-danger"><div class="inner"><h3>{{ $counts['invalid'] }}</h3><p>ผิดพลาด</p></div></div></div>
    </div>

    @if ($preview->rows !== [])
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <strong>รายการ Preview</strong>
                    <span class="text-muted ml-2">เลือกรายการพร้อมนำเข้า: <span id="customer-import-selected-count">{{ $counts['ready'] }}</span></span>
                </div>
                <label class="mb-0">แสดง <select id="customer-import-status-filter" class="form-control form-control-sm d-inline-block w-auto ml-1"><option value="all">ทั้งหมด</option><option value="ready">พร้อมนำเข้า</option><option value="review_required">ต้องตรวจสอบ</option><option value="duplicate">มีอยู่แล้ว / ซ้ำ</option><option value="invalid">ผิดพลาด</option></select></label>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm table-bordered mb-0" id="customer-import-preview-table">
                    <thead><tr><th>เลือก</th><th>แถว</th><th>C2M ID</th><th>ชื่อลูกค้า</th><th>เบอร์โทร</th><th>ที่อยู่</th><th>Tax ID</th><th>สถานะ</th><th>เหตุผล / Warning</th></tr></thead>
                    <tbody>
                        @foreach ($preview->rows as $row)
                            @php($status = $row['status'] ?? 'invalid')
                            <tr data-import-row data-status="{{ $status }}" class="{{ $status === 'invalid' ? 'table-danger' : ($status === 'duplicate' ? 'table-info' : ($status === 'review_required' ? 'table-warning' : '')) }}">
                                <td class="text-center"><input type="checkbox" name="selected_rows[]" value="{{ $row['row_number'] }}" data-import-select @checked($status === 'ready') @disabled($status !== 'ready')></td>
                                <td>{{ $row['row_number'] }}</td>
                                <td>{{ $row['external_id'] ?? '—' }}</td>
                                <td>{{ $row['name'] ?? '' }}</td>
                                <td>{{ $row['phone'] ?? '—' }}</td>
                                <td>{{ $row['address'] ?? '—' }}</td>
                                <td>{{ $row['tax_number'] ?? '—' }}</td>
                                <td>{{ ['ready' => 'พร้อมนำเข้า', 'review_required' => 'ต้องตรวจสอบ', 'duplicate' => 'มีอยู่แล้ว / ซ้ำ', 'invalid' => 'ผิดพลาด'][$status] ?? $status }}</td>
                                <td>
                                    @foreach (array_merge($row['reasons'] ?? [], $row['warnings'] ?? []) as $message)
                                        <div>{{ $message }}</div>
                                    @endforeach
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="d-flex justify-content-end mb-3">
        <button id="customer-import-confirm-button" type="submit" class="btn btn-success" @disabled($preview->rows === [] || $counts['ready'] === 0)>
            ยืนยันนำเข้า <span id="customer-import-confirm-count">{{ $counts['ready'] }}</span> รายการ
        </button>
    </div>
    </form>

    <div class="d-flex justify-content-between">
        <form method="POST" action="{{ route('customers.import.destroy', $preview->token) }}">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-light">ยกเลิก Preview</button>
        </form>
        <span class="text-muted align-self-center">รายการต้องตรวจสอบ/ซ้ำ/ผิดพลาดจะไม่ถูกเลือกอัตโนมัติ</span>
    </div>
@stop

@section('js')
    <script src="{{ asset('js/modules/customer-import-preview.js') }}"></script>
@stop
