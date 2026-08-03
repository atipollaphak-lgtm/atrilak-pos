@php
    $validRows = collect($preview->rows)->filter(fn ($row) => ($row['errors'] ?? []) === [])->count();
    $invalidRows = count($preview->rows) - $validRows;
    $hasErrors = $preview->errors !== [] || $invalidRows > 0;
@endphp

@extends('adminlte::page')

@section('title', 'ตรวจสอบสินค้านำเข้า')

@section('content_header')
    <div class="d-flex align-items-center justify-content-between">
        <div>
            <h1 class="mb-1">ตรวจสอบสินค้านำเข้า</h1>
            <p class="text-muted mb-0">{{ $preview->filename }}</p>
        </div>
        <a href="{{ route('products.import.index') }}" class="btn btn-light">ยกเลิก</a>
    </div>
@stop

@section('content')
    @if ($preview->errors !== [])
        <div class="alert alert-danger"><ul class="mb-0">@foreach ($preview->errors as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="row">
        <div class="col-md-4"><div class="small-box bg-info"><div class="inner"><h3>{{ count($preview->rows) }}</h3><p>รายการทั้งหมด</p></div></div></div>
        <div class="col-md-4"><div class="small-box bg-success"><div class="inner"><h3>{{ $validRows }}</h3><p>ผ่าน</p></div></div></div>
        <div class="col-md-4"><div class="small-box bg-danger"><div class="inner"><h3>{{ $invalidRows + count($preview->errors) }}</h3><p>ไม่ผ่าน</p></div></div></div>
    </div>

    @if ($preview->rows !== [])
        <div class="card"><div class="card-body table-responsive">
            <table class="table table-sm table-bordered">
                <thead><tr><th>แถว</th><th>ชื่อสินค้า</th><th>หมวดหมู่</th><th>หน่วยหลัก</th><th>ต้นทุน</th><th>ราคาขาย</th><th>สถานะ</th><th>ข้อผิดพลาด</th></tr></thead>
                <tbody>
                    @foreach ($preview->rows as $row)
                        <tr class="{{ ($row['errors'] ?? []) !== [] ? 'table-danger' : '' }}">
                            <td>{{ $row['row_number'] }}</td><td>{{ $row['values']['product_name'] ?? '' }}</td><td>{{ $row['values']['category'] ?? '' }}</td><td>{{ $row['values']['base_unit'] ?? '' }}</td><td>{{ $row['values']['cost_price'] ?? '' }}</td><td>{{ $row['values']['selling_price'] ?? '' }}</td><td>{{ ($row['errors'] ?? []) === [] ? 'ผ่าน' : 'ไม่ผ่าน' }}</td>
                            <td>@foreach ($row['errors'] ?? [] as $error)<div>{{ $error['column'] ?? 'ข้อมูล' }}: {{ $error['message'] ?? '' }}</div>@endforeach</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div></div>
    @endif

    <div class="d-flex justify-content-between">
        @if ($hasErrors)<a href="{{ route('products.import.errors', $preview->token) }}" class="btn btn-outline-danger">ดาวน์โหลด Error Report</a>@else<span></span>@endif
        <div class="d-flex">
            <form method="POST" action="{{ route('products.import.destroy', $preview->token) }}" class="mr-2">@csrf @method('DELETE')<button type="submit" class="btn btn-light">ยกเลิก</button></form>
            @if (! $hasErrors && $preview->rows !== [])
                <form method="POST" action="{{ route('products.import.confirm') }}">@csrf<input type="hidden" name="token" value="{{ $preview->token }}"><button type="submit" class="btn btn-success">ยืนยันนำเข้าสินค้าทั้งชุด</button></form>
            @endif
        </div>
    </div>
@stop
