@extends('adminlte::page')

@section('title', 'รายงานกำไรรายวัน')

@section('content_header')
    <h1>รายงานกำไรรายวัน</h1>
@stop

@section('content')

    <div class="card">
        <div class="card-header">
            ค้นหาตามวันที่
        </div>

        <div class="card-body">
            <form method="GET" action="{{ route('reports.daily-profit') }}">
                <div class="row">
                    <div class="col-md-4">
                        <label>วันที่</label>
                        <input type="date" name="date" class="form-control" value="{{ $date }}">
                    </div>

                    <div class="col-md-2 d-flex align-items-end">
                        <button class="btn btn-primary">
                            ค้นหา
                        </button>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <a href="{{ route('reports.daily-profit.export', ['date' => request('date', date('Y-m-d'))]) }}"
                            class="btn btn-success">
                            <i class="fas fa-file-excel"></i>
                            Export Excel
                        </a>
                    </div>
                </div>
            </form>
        </div>

    </div>

    <div class="row">

        <div class="col-md-4">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3>{{ number_format($totalSales, 2) }}</h3>
                    <p>ยอดขายรวม</p>
                </div>
                <div class="icon">
                    <i class="fas fa-cash-register"></i>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h3>{{ number_format($totalCost, 2) }}</h3>
                    <p>ต้นทุนรวม</p>
                </div>
                <div class="icon">
                    <i class="fas fa-coins"></i>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3>{{ number_format($totalProfit, 2) }}</h3>
                    <p>กำไรรวม</p>
                </div>
                <div class="icon">
                    <i class="fas fa-chart-line"></i>
                </div>
            </div>
        </div>

    </div>

    <div class="card">
        <div class="card-header">สรุปการชำระเงิน</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4"><strong>เงินสดสุทธิที่รับจากการขาย:</strong> {{ number_format($paymentSummary['cash_total'], 2) }}</div>
                <div class="col-md-4"><strong>พร้อมเพย์รวม:</strong> {{ number_format($paymentSummary['promptpay_total'], 2) }}</div>
                <div class="col-md-4"><strong>ยอดขายรวมที่มีข้อมูลการชำระ:</strong> {{ number_format($paymentSummary['recorded_total'], 2) }}</div>
            </div>
            <div class="mt-2">
                จำนวนบิลเงินสด: {{ $paymentSummary['cash_count'] }} |
                จำนวนบิลพร้อมเพย์: {{ $paymentSummary['promptpay_count'] }} |
                จำนวนบิลแบบผสม: {{ $paymentSummary['mixed_count'] }} |
                ไม่ระบุวิธีชำระ: {{ $paymentSummary['unrecorded_count'] }}
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            รายการขายวันที่ {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}
        </div>

        <div class="card-body">

            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>เลขที่บิล</th>
                        <th>ลูกค้า</th>
                        <th class="text-right">ยอดขาย</th>
                        <th class="text-right">ต้นทุน</th>
                        <th class="text-right">กำไร</th>
                        <th>ดูบิล</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($sales as $sale)
                        @php($financial = $financialsBySaleId[$sale->id])

                        <tr>
                            <td>{{ $sale->sale_no }}</td>
                            <td>{{ $sale->customer->name ?? 'ลูกค้าทั่วไป' }}</td>
                            <td class="text-right">{{ number_format($financial['revenue'], 2) }}</td>
                            <td class="text-right">{{ number_format($financial['cost'], 2) }}</td>
                            <td class="text-right">{{ number_format($financial['profit'], 2) }}</td>
                            <td>
                                <a href="{{ route('sales.show', $sale) }}" class="btn btn-sm btn-info">
                                    ดูบิล
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center">
                                ไม่มีรายการขายในวันนี้
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                <tfoot>
                    <tr>
                        <th colspan="2" class="text-right">รวม</th>
                        <th class="text-right">{{ number_format($totalSales, 2) }}</th>
                        <th class="text-right">{{ number_format($totalCost, 2) }}</th>
                        <th class="text-right">{{ number_format($totalProfit, 2) }}</th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>

        </div>
    </div>

@stop
