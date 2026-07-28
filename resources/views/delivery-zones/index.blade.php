@extends('adminlte::page')

@section('title', 'ราคาตามโซน')

@section('content_header')
    <h1>ราคาตามโซน</h1>
@stop

@section('content')

    <div class="card">

        <div class="card-header d-flex justify-content-between align-items-center">

            <h3 class="card-title">
                รายการราคาตามโซน
            </h3>

            <a href="{{ route('delivery-zones.create') }}" class="btn btn-primary">
                <i class="fas fa-plus"></i>
                เพิ่มโซน
            </a>

        </div>

        <div class="card-body">

            <table class="table table-bordered table-hover">

                <thead>

                    <tr>
                        <th width="80">ลำดับ</th>
                        <th>ชื่อโซน</th>
                        <th width="140">เพิ่มราคาสินค้า</th>
                        <th width="150">กำไรขั้นต่ำ</th>
                        <th width="100">สถานะ</th>
                        <th width="150">จัดการ</th>
                    </tr>

                </thead>

                <tbody>

                    @forelse($deliveryZones as $zone)
                        <tr>

                            <td>{{ $zone->sort_order }}</td>

                            <td>{{ $zone->name }}</td>

                            <td class="text-end">{{ number_format($zone->price_markup_percent ?? 0, 2) }}%</td>

                            <td class="text-end">
                                {{ number_format($zone->minimum_profit ?? 0, 2) }}
                            </td>

                            <td>

                                @if ($zone->active)
                                    <span class="badge bg-success">
                                        ใช้งาน
                                    </span>
                                @else
                                    <span class="badge bg-danger">
                                        ปิด
                                    </span>
                                @endif

                            </td>

                            <td>

                                <a href="{{ route('delivery-zones.edit', $zone) }}" class="btn btn-sm btn-warning">
                                    ดู/แก้ไข
                                </a>

                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td colspan="6" class="text-center">
                                ยังไม่มีข้อมูล
                            </td>

                        </tr>
                    @endforelse

                </tbody>

            </table>

        </div>

    </div>

@stop
