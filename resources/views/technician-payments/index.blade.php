@extends('adminlte::page')

@section('title', 'จ่ายค่าช่าง')

@section('content_header')
    <h1>จ่ายค่าช่าง</h1>
@stop

@section('content')

    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-header">
            เลือกเดือน
        </div>

        <div class="card-body">
            <form method="GET" action="{{ route('technician-payments.index') }}">
                <div class="row">
                    <div class="col-md-3">
                        <label>เดือน</label>
                        <input
                            type="month"
                            name="month"
                            class="form-control"
                            value="{{ $month }}"
                        >
                    </div>

                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">
                            ค้นหา
                        </button>

                        <a
                            href="{{ route('technician-payment-batches.create') }}"
                            class="btn btn-success ml-2"
                        >
                            สร้างรอบจ่ายค่าช่าง
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            รายการค่าช่างรอจ่าย
        </div>

        <div class="card-body table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th style="width: 60px;">#</th>
                        <th>ช่าง</th>
                        <th class="text-right">ยอดค่าช่างรอจ่าย</th>
                        <th style="width: 180px;">จัดการ</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($summaries as $summary)
                        <tr>
                            <td>{{ $loop->iteration }}</td>

                            <td>
                                {{ $summary->technician->name ?? '-' }}
                            </td>

                            <td class="text-right">
                                {{ number_format($summary->total_commission, 2) }}
                            </td>

                            <td>
                                <form
                                    method="POST"
                                    action="{{ route('technician-payments.pay') }}"
                                    onsubmit="return confirm('ยืนยันจ่ายค่าช่างรายการนี้?')"
                                >
                                    @csrf

                                    <input
                                        type="hidden"
                                        name="technician_id"
                                        value="{{ $summary->technician_id }}"
                                    >

                                    <input
                                        type="hidden"
                                        name="month"
                                        value="{{ $month }}"
                                    >

                                    <button type="submit" class="btn btn-sm btn-success">
                                        จ่ายรายการนี้
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted">
                                ไม่พบรายการค่าช่างรอจ่ายในเดือนนี้
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                <tfoot>
                    <tr>
                        <th colspan="2" class="text-right">
                            รวมทั้งหมด
                        </th>
                        <th class="text-right">
                            {{ number_format($summaries->sum('total_commission'), 2) }}
                        </th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

@stop
