@extends('adminlte::page')

@section('title', 'สร้างรอบจ่ายค่าช่าง')

@section('content_header')
    <h1>สร้างรอบจ่ายค่าช่าง</h1>
@endsection

@section('content')

    @if (session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card">

        <div class="card-header">
            ข้อมูลรอบจ่าย
        </div>

        <div class="card-body">

            <form method="POST" action="{{ route('technician-payment-batches.preview') }}">
                @csrf

                <div class="row">
                    <div class="col-md-4">
                        <label>วันที่จ่าย</label>
                        <input type="date" name="payment_date" class="form-control"
                            value="{{ old('payment_date', $paymentDate ?? date('Y-m-d')) }}">
                    </div>

                    <div class="col-md-8">
                        <label>หมายเหตุ</label>
                        <input type="text" name="remark" class="form-control"
                            value="{{ old('remark', $remark ?? '') }}">
                    </div>
                </div>

                <hr>

                <h5>เลือกช่างที่ต้องการจ่าย</h5>

                @error('technician_ids')
                    <div class="alert alert-danger">
                        กรุณาเลือกช่างอย่างน้อย 1 คน
                    </div>
                @enderror

                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th width="80" class="text-center">เลือก</th>
                            <th>ชื่อช่าง</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($technicians as $technician)
                            <tr>
                                <td class="text-center">
                                    <input type="checkbox" name="technician_ids[]" value="{{ $technician->id }}"
                                        {{ in_array($technician->id, old('technician_ids', $selectedTechnicianIds ?? [])) ? 'checked' : '' }}>
                                </td>
                                <td>{{ $technician->name }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="text-right">
                    <a href="{{ route('technician-payment-batches.index') }}" class="btn btn-secondary">
                        กลับ
                    </a>

                    <button type="submit" class="btn btn-primary">
                        โหลดรายการค้างจ่าย
                    </button>
                </div>

            </form>

        </div>
    </div>

    @if (!empty($preview))
        <div class="card mt-3">

            <div class="card-header bg-info">
                Preview รายการค้างจ่าย
            </div>

            <div class="card-body">

                @if ($preview['total_items'] == 0)

                    <div class="alert alert-warning">
                        ไม่พบรายการค่าช่างค้างจ่ายของช่างที่เลือก
                    </div>
                @else
                    @foreach ($preview['groups'] as $group)
                        <div class="card mb-3">

                            <div class="card-header">
                                <strong>{{ $group['technician']->name }}</strong>

                                <span class="float-right">
                                    รวม {{ number_format($group['total_amount'], 2) }} บาท
                                </span>
                            </div>

                            <div class="card-body p-0">
                                <table class="table table-bordered mb-0">
                                    <thead>
                                        <tr>
                                            <th>เลขที่บิล</th>
                                            <th class="text-right">ยอดขาย</th>
                                            <th class="text-right">ค่าช่าง</th>
                                            <th>สถานะ</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        @foreach ($group['items'] as $item)
                                            <tr>
                                                <td>
                                                    {{ $item->sale->sale_no ?? '-' }}
                                                </td>

                                                <td class="text-right">
                                                    {{ number_format($item->sale->total_amount ?? 0, 2) }}
                                                </td>

                                                <td class="text-right">
                                                    {{ number_format($item->commission_amount, 2) }}
                                                </td>

                                                <td>
                                                    <span class="badge badge-warning">
                                                        pending
                                                    </span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>

                                    <tfoot>
                                        <tr>
                                            <th colspan="2" class="text-right">
                                                รวม
                                            </th>
                                            <th class="text-right">
                                                {{ number_format($group['total_amount'], 2) }}
                                            </th>
                                            <th></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                        </div>
                    @endforeach

                    <div class="card bg-light">
                        <div class="card-body">
                            <h5>สรุปรอบจ่าย</h5>

                            <p>
                                จำนวนช่าง:
                                <strong>{{ $preview['total_technicians'] }}</strong>
                            </p>

                            <p>
                                จำนวนรายการ:
                                <strong>{{ $preview['total_items'] }}</strong>
                            </p>

                            <p>
                                ยอดรวมทั้งหมด:
                                <strong>{{ number_format($preview['total_amount'], 2) }}</strong>
                                บาท
                            </p>

                            <form method="POST" action="{{ route('technician-payment-batches.store') }}">
                                @csrf

                                <input type="hidden" name="payment_date" value="{{ $paymentDate ?? date('Y-m-d') }}">
                                <input type="hidden" name="remark" value="{{ $remark ?? '' }}">

                                @foreach ($selectedTechnicianIds ?? [] as $technicianId)
                                    <input type="hidden" name="technician_ids[]" value="{{ $technicianId }}">
                                @endforeach

                                <button type="submit" class="btn btn-success"
                                    onclick="return confirm('ยืนยันสร้างรอบจ่ายค่าช่างนี้ใช่ไหม?')">
                                    ยืนยันสร้างรอบจ่าย
                                </button>
                            </form>
                        </div>
                    </div>

                @endif

            </div>
        </div>
    @endif

@endsection
