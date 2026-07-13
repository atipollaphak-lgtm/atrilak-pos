@extends('adminlte::page')

@section('title', 'รายงานสินค้าขายดี')

@section('content_header')
    <h1>รายงานสินค้าขายดี</h1>
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
                        @for($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>
                                {{ $m }}
                            </option>
                        @endfor
                    </select>
                </div>

                <div class="col-md-3">
                    <label>ปี</label>

                    <input
                        type="number"
                        name="year"
                        class="form-control"
                        value="{{ $year }}">
                </div>

                <div class="col-md-3">
                    <label>&nbsp;</label>

                    <button class="btn btn-primary d-block">
                        แสดงรายงาน
                    </button>
                </div>
<div class="col-md-2 d-flex align-items-end">
    <a href="{{ route('reports.best-seller.export', [
            'month' => request('month', date('m')),
            'year' => request('year', date('Y'))
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
        Top 10 สินค้าขายดี
    </div>

    <div class="card-body">

        <table class="table table-bordered">
            <thead>
                <tr>
                    <th width="80">อันดับ</th>
                    <th>สินค้า</th>
                    <th class="text-right">จำนวนขาย</th>
                </tr>
            </thead>

            <tbody>
                @forelse($products as $index => $product)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $product['name'] }}</td>
                        <td class="text-right">
                            {{ number_format($product['qty'], 2) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="text-center">
                            ไม่พบข้อมูล
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

    </div>
</div>

@stop
