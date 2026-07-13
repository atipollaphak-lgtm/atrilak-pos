@extends('adminlte::page')

@section('title', 'รายงานกำไรรายเดือน')

@section('content_header')
    <h1>รายงานกำไรรายเดือน</h1>
@stop

@section('content')

    <div class="card">
        <div class="card-header">ค้นหาตามเดือน</div>

        <div class="card-body">
            <form method="GET" action="{{ route('reports.monthly-profit') }}">
                <div class="row">
                    <div class="col-md-3">
                        <label>เดือน</label>
                        <select name="month" class="form-control">
                            @for ($m = 1; $m <= 12; $m++)
                                <option value="{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}"
                                    {{ $month == str_pad($m, 2, '0', STR_PAD_LEFT) ? 'selected' : '' }}>
                                    {{ $m }}
                                </option>
                            @endfor
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label>ปี</label>
                        <input type="number" name="year" class="form-control" value="{{ $year }}">
                    </div>

                    <div class="col-md-2 d-flex align-items-end">
                        <button class="btn btn-primary">
                            ค้นหา
                        </button>
                    </div>

                    <div class="col-md-2 d-flex align-items-end">
                        <a href="{{ route('reports.monthly-profit.export', [
                            'month' => request('month', date('m')),
                            'year' => request('year', date('Y')),
                        ]) }}"
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
                <div class="icon"><i class="fas fa-cash-register"></i></div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h3>{{ number_format($totalCost, 2) }}</h3>
                    <p>ต้นทุนรวม</p>
                </div>
                <div class="icon"><i class="fas fa-coins"></i></div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3>{{ number_format($totalProfit, 2) }}</h3>
                    <p>กำไรรวม</p>
                </div>
                <div class="icon"><i class="fas fa-chart-line"></i></div>
            </div>
        </div>

    </div>

    <div class="card">
        <div class="card-header">
            รายการขายเดือน {{ $month }}/{{ $year }}
        </div>

        <div class="card-body">

            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>วันที่</th>
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
                        @php
                            $cost = $sale->items->sum(function ($item) {
                                return $item->cost_price * $item->qty;
                            });

                            $profit = $sale->items->sum('profit');
                        @endphp

                        <tr>
                            <td>{{ \Carbon\Carbon::parse($sale->sale_date)->format('d/m/Y') }}</td>
                            <td>{{ $sale->sale_no }}</td>
                            <td>{{ $sale->customer->name ?? 'ลูกค้าทั่วไป' }}</td>
                            <td class="text-right">{{ number_format($sale->total_amount, 2) }}</td>
                            <td class="text-right">{{ number_format($cost, 2) }}</td>
                            <td class="text-right">{{ number_format($profit, 2) }}</td>
                            <td>
                                <a href="{{ route('sales.show', $sale) }}" class="btn btn-sm btn-info">
                                    ดูบิล
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center">
                                ไม่มีรายการขายในเดือนนี้
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                <tfoot>
                    <tr>
                        <th colspan="3" class="text-right">รวม</th>
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
