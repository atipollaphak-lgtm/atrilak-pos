@extends('adminlte::page')

@section('title', 'รายงานยอดขายสินค้า')

@section('content_header')
    <h1>รายงานยอดขายสินค้า</h1>
@stop

@section('content')

    <div class="card">
        <div class="card-header">
            ค้นหารายงาน
        </div>

        <div class="card-body">
            <form method="GET">
                <div class="row">

                    <div class="col-md-3">
                        <label>เดือน</label>
                        <select name="month" class="form-control">
                            @for ($m = 1; $m <= 12; $m++)
                                <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>
                                    {{ $m }}
                                </option>
                            @endfor
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label>ปี</label>
                        <input type="number" name="year" class="form-control" value="{{ $year }}">
                    </div>

                    <div class="col-md-3">
                        <label>&nbsp;</label>
                        <button class="btn btn-primary d-block">
                            แสดงรายงาน
                        </button>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <a href="{{ route('reports.product-sales.export', [
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

    <div class="card">
        <div class="card-header">
            รายการยอดขายสินค้า
        </div>

        <div class="card-body">

            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>สินค้า</th>
                        <th>หน่วย</th>
                        <th class="text-right">จำนวนขาย</th>
                        <th class="text-right">ยอดขาย</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($products as $product)
                        <tr>
                            <td>{{ $product['name'] }}</td>
                            <td>{{ $product['unit'] }}</td>
                            <td class="text-right">{{ number_format($product['qty'], 2) }}</td>
                            <td class="text-right">{{ number_format($product['sales'], 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center">
                                ไม่พบข้อมูล
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

        </div>
    </div>

@stop
