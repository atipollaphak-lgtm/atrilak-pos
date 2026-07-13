@extends('adminlte::page')

@section('title', 'ประวัติค่าช่าง')

@section('content_header')
    <h1>ประวัติค่าช่าง</h1>
@stop

@section('content')

    <div class="card mb-3">
        <div class="card-header">
            ค้นหาตามเดือน
        </div>

        <div class="card-body">
            <form method="GET" action="{{ route('technician-commissions.index') }}">
                <div class="row">
                    <div class="col-md-4">
                        <label>เดือน</label>
                        <input type="month" name="month" value="{{ $month }}" class="form-control">
                    </div>

                    <div class="col-md-2">
                        <label>&nbsp;</label>
                        <button class="btn btn-primary btn-block">
                            ค้นหา
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header bg-success text-white">
            สรุปรวมค่าช่างตามช่าง
        </div>

        <div class="card-body">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>ช่าง</th>
                        <th class="text-right">ยอดขายรวม</th>
                        <th class="text-right">ค่าช่างรวม</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($summaryByTechnician as $summary)
                        <tr>
                            <td>{{ $summary->technician->name ?? '-' }}</td>
                            <td class="text-right">{{ number_format($summary->total_sales, 2) }}</td>
                            <td class="text-right">{{ number_format($summary->total_commission, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center">
                                ไม่มีข้อมูลค่าช่างในเดือนนี้
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            รายการค่าช่างทั้งหมด
        </div>

        <div class="card-body">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th width="60">#</th>
                        <th>วันที่</th>
                        <th>ช่าง</th>
                        <th>เลขที่บิล</th>
                        <th class="text-right">ยอดขาย</th>
                        <th class="text-right">อัตรา</th>
                        <th class="text-right">ค่าช่าง</th>
                        <th>สถานะ</th>
                        <th>หมายเหตุ</th>
                        <th>รายละเอียด</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($commissions as $index => $commission)
                        <tr>
                            <td class="text-center">
                                {{ $index + 1 }}
                            </td>

                            <td>{{ $commission->commission_date }}</td>
                            <td>{{ $commission->technician->name ?? '-' }}</td>
                            <td>
                                @if ($commission->sale)
                                    <a href="{{ route('sales.show', $commission->sale->id) }}" target="_blank">
                                        {{ $commission->sale->sale_no }}
                                    </a>
                                @else
                                    -
                                @endif
                            </td>
                            <td class="text-right">{{ number_format($commission->sale_total, 2) }}</td>
                            <td class="text-right">{{ number_format($commission->commission_rate, 2) }}%</td>
                            <td class="text-right">{{ number_format($commission->commission_amount, 2) }}</td>
                            <td>
                                @if ($commission->status === 'paid')
                                    <span class="badge badge-success">จ่ายแล้ว</span>
                                @elseif ($commission->status === 'pending')
                                    <span class="badge badge-warning">รอจ่าย</span>
                                @else
                                    <span class="badge badge-secondary">
                                        {{ $commission->status }}
                                    </span>
                                @endif
                            </td>
                            <td>{{ $commission->remark }}</td>โ
                            <td>
                                @if ($commission->calculation_detail)
                                    <button type="button" class="btn btn-info btn-sm" data-toggle="modal"
                                        data-target="#detailModal{{ $commission->id }}">
                                        ดูรายละเอียด
                                    </button>
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center">
                                ไม่มีรายการค่าช่าง
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @foreach ($commissions as $commission)
        <div class="modal fade" id="detailModal{{ $commission->id }}" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">

                    <div class="modal-header">
                        <h5 class="modal-title">
                            รายละเอียดค่าช่าง {{ $commission->sale->sale_no ?? '-' }}
                        </h5>

                        <button type="button" class="close" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                    </div>

                    <div class="modal-body">

                        @php
                            $details = json_decode($commission->calculation_detail, true) ?? [];
                        @endphp

                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>สินค้า</th>
                                    <th class="text-right">จำนวน</th>
                                    <th class="text-right">ยอดขาย</th>
                                    <th>กฎที่ใช้</th>
                                    <th class="text-right">ค่าช่าง</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($details as $detail)
                                    <tr>
                                        <td>{{ $detail['product_name'] ?? '-' }}</td>
                                        <td class="text-right">{{ number_format($detail['qty'] ?? 0, 2) }}</td>
                                        <td class="text-right">{{ number_format($detail['line_total'] ?? 0, 2) }}</td>
                                        <td>
                                            {{ $detail['rule_name'] ?? '-' }}

                                            @if (($detail['rule_type'] ?? '') === 'percent')
                                                ({{ number_format($detail['rule_value'] ?? 0, 2) }}%)
                                            @else
                                                ({{ number_format($detail['rule_value'] ?? 0, 2) }} บาท/หน่วย)
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            {{ number_format($detail['commission'] ?? 0, 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <h4 class="text-right">
                            รวมค่าช่าง:
                            {{ number_format($commission->commission_amount, 2) }}
                            บาท
                        </h4>

                    </div>

                </div>
            </div>
        </div>
    @endforeach
@stop
