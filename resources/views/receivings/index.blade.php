@extends('adminlte::page')

@section('title', 'รับสินค้าเข้า V2')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1>รับสินค้าเข้า V2</h1>
        <a href="{{ route('receivings.create') }}" class="btn btn-primary">+ รับสินค้าเข้า</a>
    </div>
@stop

@section('content')
    @include('partials.flash-messages')
    <div class="card">
        <div class="card-header">ประวัติการรับสินค้า</div>
        <div class="card-body">
            <form method="GET" class="row g-2 mb-3">
                <div class="col-md-2">
                    <select name="source" class="form-control">
                        <option value="">ทุกแหล่งที่มา</option>
                        <option value="supplier" @selected(request('source') === 'supplier')>ซื้อจาก Supplier</option>
                        <option value="production" @selected(request('source') === 'production')>ผลิตเอง</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="supplier_id" class="form-control">
                        <option value="">ทุก Supplier</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected((string) request('supplier_id') === (string) $supplier->id)>{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3"><input name="search" class="form-control" placeholder="เลขที่เอกสาร/ชื่อ Supplier" value="{{ request('search') }}"></div>
                <div class="col-md-2"><input type="date" name="from" class="form-control" value="{{ request('from') }}"></div>
                <div class="col-md-2"><button class="btn btn-outline-primary">ค้นหา</button></div>
            </form>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead><tr><th>วันที่</th><th>แหล่งที่มา</th><th>Supplier</th><th>เลขที่เอกสาร</th><th>ยอดรวม</th><th>สถานะ</th></tr></thead>
                    <tbody>
                    @forelse ($receivings as $receiving)
                        <tr>
                            <td><a href="{{ route('receivings.show', $receiving) }}">{{ $receiving->purchase_date ? \Illuminate\Support\Carbon::parse($receiving->purchase_date)->format('Y-m-d') : '-' }}</a></td>
                            <td>{{ $receiving->display_source === 'production' ? 'ผลิตเอง' : 'ซื้อจาก Supplier' }}</td>
                            <td>{{ $receiving->supplier?->name ?: '-' }}</td>
                            <td>{{ $receiving->supplier_document_number ?: '-' }}</td>
                            <td class="text-right">{{ number_format((float) $receiving->total_amount, 2) }}</td>
                            <td>{{ $receiving->display_status }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">ยังไม่มีประวัติ</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            {{ $receivings->links() }}
        </div>
    </div>
@stop
