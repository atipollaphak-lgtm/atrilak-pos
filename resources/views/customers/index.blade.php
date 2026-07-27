@extends('adminlte::page')

@section('title', 'ลูกค้า')
@section('content_header')<h1>ลูกค้า</h1>@stop

@section('content')
    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">รายชื่อลูกค้า</h3>
            <a href="{{ route('customers.create') }}" class="btn btn-primary btn-sm">เพิ่มลูกค้า</a>
        </div>
        <div class="card-body">
            <form method="GET" class="row mb-3">
                <div class="col-md-6 mb-2"><label for="customer-search">ค้นหาลูกค้า</label><input id="customer-search" name="search" value="{{ $search }}" class="form-control" placeholder="รหัส ชื่อ เบอร์ โซน หรือที่อยู่"></div>
                <div class="col-md-3 mb-2"><label for="customer-sort">เรียงตาม</label><select id="customer-sort" name="sort" class="form-control"><option value="zone" @selected($sort === 'zone')>พื้นที่จัดส่ง</option><option value="name" @selected($sort === 'name')>ชื่อลูกค้า</option><option value="created_at" @selected($sort === 'created_at')>วันที่สร้าง</option></select></div>
                <div class="col-md-2 mb-2"><label for="customer-direction">ทิศทาง</label><select id="customer-direction" name="direction" class="form-control"><option value="asc" @selected($direction === 'asc')>น้อยไปมาก</option><option value="desc" @selected($direction === 'desc')>มากไปน้อย</option></select></div>
                <div class="col-md-1 mb-2 d-flex align-items-end"><button class="btn btn-secondary btn-block">ค้นหา</button></div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead><tr><th>รหัสลูกค้า</th><th>ชื่อลูกค้า</th><th>พื้นที่จัดส่ง</th><th>รายละเอียดที่อยู่</th><th>เบอร์ลูกค้า</th><th>จัดการ</th></tr></thead>
                    <tbody>
                    @forelse ($customers as $customer)
                        <tr>
                            <td>{{ $customer->code ?: '—' }}</td>
                            <td><a href="{{ route('customers.show', $customer) }}">{{ $customer->name }}</a></td>
                            <td>{{ $customer->defaultDeliveryAddress?->deliveryZone?->name ?? 'ไม่ระบุพื้นที่' }}</td>
                            <td>{{ $customer->defaultDeliveryAddress?->address ?? '—' }}</td>
                            <td>{{ $customer->phone ?: '—' }}</td>
                            <td><a href="{{ route('customers.show', $customer) }}" class="btn btn-info btn-sm">ดูรายละเอียด</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center">ไม่พบลูกค้า</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            {{ $customers->links() }}
        </div>
    </div>
@stop
