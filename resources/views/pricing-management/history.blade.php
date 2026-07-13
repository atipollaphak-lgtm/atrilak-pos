@extends('adminlte::page')

@section('title', 'ประวัติการปรับราคา')

@section('content_header')
    <h1>ประวัติการปรับราคา</h1>
@stop

@section('content')

    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">

            <strong>ประวัติการปรับราคา</strong>

            <span class="badge badge-primary">

                ทั้งหมด {{ $histories->total() }} รายการ

            </span>

        </div>

        <div class="card-body">

            <form method="GET">

                <div class="row">

                    <div class="col-md-3 mb-3">
                        <label>วันที่เริ่ม</label>
                        <input type="date" name="from" class="form-control" value="{{ request('from') }}">
                    </div>

                    <div class="col-md-3 mb-3">
                        <label>วันที่สิ้นสุด</label>
                        <input type="date" name="to" class="form-control" value="{{ request('to') }}">
                    </div>

                    <div class="col-md-3 mb-3">
                        <label>ผู้ใช้งาน</label>
                        <input type="text" name="user" class="form-control" placeholder="ชื่อผู้ใช้งาน"
                            value="{{ request('user') }}">
                    </div>

                    <div class="col-md-3 mb-3">
                        <label>สินค้า</label>
                        <input type="text" name="product" class="form-control" placeholder="ชื่อสินค้า"
                            value="{{ request('product') }}">
                    </div>

                </div>

                <button type="submit" class="btn btn-primary">
                    ค้นหา
                </button>

                <a href="{{ route('pricing-management.history') }}" class="btn btn-secondary">
                    ล้างตัวกรอง
                </a>

            </form>

        </div>
    </div>

    <div class="card">

        <div class="card-header">
            <strong>ประวัติการปรับราคา</strong>
        </div>

        <div class="card-body p-0">

            <div class="table-responsive">

                <table class="table table-bordered table-hover mb-0">

                    <thead class="thead-light">

                        <tr>
                            <th width="70">#</th>
                            <th>วันที่</th>
                            <th>สินค้า</th>
                            <th class="text-right">ราคาเดิม</th>
                            <th class="text-right">ราคาใหม่</th>
                            <th>ผู้ใช้งาน</th>
                            <th>ประเภท</th>
                            <th width="130" class="text-center">
                                จัดการ
                            </th>
                        </tr>

                    </thead>

                    <tbody>

                        @forelse($histories as $history)
                            <tr>

                                <td>{{ $history->id }}</td>

                                <td>
                                    {{ $history->created_at?->format('d/m/Y H:i') }}
                                </td>

                                <td>
                                    {{ $history->product?->name }}
                                </td>

                                <td class="text-right">
                                    {{ number_format($history->old_price, 2) }}
                                </td>

                                <td class="text-right">
                                    {{ number_format($history->new_price, 2) }}
                                </td>

                                <td>
                                    {{ $history->user?->name ?? '-' }}
                                </td>

                                <td>

                                    @switch($history->created_from)
                                        @case('manual_apply')
                                            <span class="badge badge-success">
                                                Manual
                                            </span>
                                        @break

                                        @case('bulk_apply')
                                            <span class="badge badge-primary">
                                                Bulk
                                            </span>
                                        @break

                                        @case('category_bulk')
                                            <span class="badge badge-info">
                                                Category Bulk
                                            </span>
                                        @break

                                        @case('rollback')
                                            <span class="badge badge-warning">
                                                Rollback
                                            </span>
                                        @break

                                        @default
                                            <span class="badge badge-secondary">
                                                {{ $history->created_from }}
                                            </span>
                                    @endswitch

                                </td>

                                <td class="text-center">

                                    @if ($history->created_from !== 'rollback')
                                        <form method="POST" action="{{ route('pricing-management.rollback', $history) }}"
                                            onsubmit="return confirm('ยืนยันย้อนราคาสินค้านี้หรือไม่?')">

                                            @csrf

                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                Rollback
                                            </button>

                                        </form>
                                    @else
                                        <span class="badge badge-secondary">
                                            Rollback แล้ว
                                        </span>
                                    @endif

                                </td>

                            </tr>

                            @empty

                                <tr>

                                    <td colspan="8" class="text-center text-muted py-5">
                                        ยังไม่มีข้อมูลประวัติการปรับราคา
                                    </td>

                                </tr>
                            @endforelse

                        </tbody>

                    </table>

                </div>

            </div>

            @if (
                $histories instanceof \Illuminate\Contracts\Pagination\Paginator ||
                    $histories instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
                <div class="card-footer">

                    {{ $histories->withQueryString()->links() }}

                </div>
            @endif

        </div>

        </div>

    @stop
