@extends('adminlte::page')

@section('title', 'Dashboard')

@section('content_header')
    <h1>Dashboard</h1>
@stop

@section('content')

    <div class="row">

        <div class="col-md-3">
            <div class="small-box bg-primary">
                <div class="inner">
                    <h3>{{ number_format($todaySales, 2) }}</h3>
                    <p>ยอดขายวันนี้</p>
                </div>
                <div class="icon">
                    <i class="fas fa-cash-register"></i>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3>{{ number_format($todayProfit, 2) }}</h3>
                    <p>กำไรวันนี้</p>
                </div>
                <div class="icon">
                    <i class="fas fa-chart-line"></i>
                </div>
            </div>
        </div>





        <div class="col-md-3">
            <div class="small-box bg-indigo">
                <div class="inner">
                    <h3>{{ number_format($monthSales, 2) }}</h3>
                    <p>ยอดขายเดือนนี้</p>
                </div>
                <div class="icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="small-box bg-purple">
                <div class="inner">
                    <h3>{{ number_format($monthProfit, 2) }}</h3>
                    <p>กำไรเดือนนี้</p>
                </div>
                <div class="icon">
                    <i class="fas fa-chart-line"></i>
                </div>
            </div>
        </div>

    </div>
    <div class="row">

        <div class="col-md-3">
            <div class="small-box bg-secondary">
                <div class="inner">
                    <h3>{{ $todaySaleCount }}</h3>
                    <p>จำนวนบิลวันนี้</p>
                </div>
                <div class="icon">
                    <i class="fas fa-file-invoice"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3>{{ number_format($totalProducts) }}</h3>
                    <p>จำนวนสินค้า</p>
                </div>
                <div class="icon">
                    <i class="fas fa-box"></i>
                </div>
                <a href="{{ route('products.index') }}" class="small-box-footer">
                    ไปหน้าสินค้า <i class="fas fa-arrow-circle-right"></i>
                </a>
            </div>
        </div>

        <div class="col-md-3">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3>{{ $totalCustomers }}</h3>
                    <p>ลูกค้าทั้งหมด</p>
                </div>
                <div class="icon">
                    <i class="fas fa-users"></i>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h3>{{ $totalSuppliers }}</h3>
                    <p>ผู้จำหน่ายทั้งหมด</p>
                </div>
                <div class="icon">
                    <i class="fas fa-truck"></i>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h3>{{ number_format($lowStockCount) }}</h3>
                    <p>สินค้าใกล้หมด</p>
                </div>
                <div class="icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <a href="{{ route('products.index') }}" class="small-box-footer">
                    ตรวจสอบสินค้า <i class="fas fa-arrow-circle-right"></i>
                </a>
            </div>
        </div>

        <div class="col-md-4">
            <div class="small-box bg-danger">
                <div class="inner">
                    <h3>{{ $outOfStockCount }}</h3>
                    <p>สินค้าหมดสต๊อก</p>
                </div>
                <div class="icon">
                    <i class="fas fa-times-circle"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="small-box bg-info">

                <div class="inner">
                    <h3>{{ number_format($stockValue, 2) }}</h3>
                    <p>มูลค่าสต๊อกคงเหลือ</p>
                </div>

                <div class="icon">
                    <i class="fas fa-warehouse"></i>
                </div>

            </div>
        </div>
    </div>

    <div class="card">

        <div class="card-header bg-primary">
            <h3 class="card-title">
                ยอดขาย 7 วันล่าสุด
            </h3>
        </div>

        <div class="card-body">

            <div style="height: 220px;">
                <canvas id="salesChart"></canvas>
            </div>

        </div>

    </div>
    <div class="card mt-3">

        <div class="card-header bg-success">
            <h3 class="card-title">
                Top 10 สินค้าขายดีเดือนนี้
            </h3>
        </div>

        <div class="card-body">

            @if (count($bestProducts) > 0)

                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th width="80">อันดับ</th>
                            <th>สินค้า</th>
                            <th width="150">จำนวนขาย</th>
                            <th width="120">หน่วย</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($bestProducts as $index => $product)
                            <tr>
                                <td>
                                    @if ($index == 0)
                                        🥇
                                    @elseif ($index == 1)
                                        🥈
                                    @elseif ($index == 2)
                                        🥉
                                    @else
                                        {{ $index + 1 }}
                                    @endif
                                </td>

                                <td>{{ $product['name'] }}</td>
                                <td>{{ number_format($product['qty']) }}</td>
                                <td>{{ $product['unit'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="alert alert-info mb-0">
                    ยังไม่มีข้อมูลสินค้าขายดีเดือนนี้
                </div>

            @endif

        </div>

    </div>
    <div class="card">

        <div class="card-header bg-danger">
            <h3 class="card-title">
                สินค้าใกล้หมด
            </h3>
        </div>

        <div class="card-body">

            @if ($lowStockProducts->count() > 0)

                <table class="table table-bordered table-striped">

                    <thead>
                        <tr>
                            <th>สินค้า</th>
                            <th>หมวด</th>
                            <th>คงเหลือ</th>
                            <th>ขั้นต่ำ</th>
                            <th>หน่วย</th>
                        </tr>
                    </thead>

                    <tbody>

                        @foreach ($lowStockProducts as $product)
                            <tr>
                                <td>{{ $product->name }}</td>
                                <td>{{ $product->category->name ?? '-' }}</td>
                                <td>
                                    @if ($product->stock_qty == 0)
                                        <span class="badge badge-danger">
                                            หมดสต๊อก
                                        </span>
                                    @else
                                        <span
                                            class="badge @if ($product->stock_qty == 0) badge-danger
@elseif($product->stock_qty <= 3)
badge-warning
@else
badge-info @endif">
                                            {{ $product->stock_qty }}
                                        </span>
                                    @endif
                                </td>
                                <td>{{ $product->minimum_stock }}</td>
                                <td>
                                    {{ $product->unitRelation->name ?? '-' }}
                                </td>
                            </tr>
                        @endforeach

                    </tbody>

                </table>
            @else
                <div class="alert alert-success mb-0">
                    ยังไม่มีสินค้าใกล้หมด
                </div>

            @endif

        </div>

    </div>

    </div>

@stop

@section('js')

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        const ctx =
            document.getElementById(
                'salesChart'
            );

        new Chart(ctx, {

            type: 'line',

            data: {

                labels: @json($chartLabels),

                datasets: [{

                    label: 'ยอดขาย',

                    data: @json($chartSales),

                    borderWidth: 3,

                    tension: 0.3,

                    fill: false

                }]
            },

            options: {

                responsive: true,

                maintainAspectRatio: false,

                plugins: {

                    legend: {
                        display: true
                    }

                }

            }

        });
    </script>


@stop
