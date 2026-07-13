@extends('adminlte::page')

@section('title', 'รายละเอียดรอบจ่ายค่าช่าง')

@section('content_header')
    <h1>รายละเอียดรอบจ่ายค่าช่าง</h1>
@endsection

@section('content')

<div class="card">
    <div class="card-header">
        <strong>เลขที่รอบจ่าย:</strong> {{ $batch->batch_no }}
    </div>

    <div class="card-body">

        <p><strong>วันที่จ่าย:</strong> {{ $batch->payment_date }}</p>
        <p><strong>จำนวนช่าง:</strong> {{ $batch->total_technicians }}</p>
        <p><strong>จำนวนรายการ:</strong> {{ $batch->total_items }}</p>
        <p><strong>ยอดรวม:</strong> {{ number_format($batch->total_amount, 2) }} บาท</p>

        <a href="{{ route('technician-payment-batches.print', $batch->id) }}"
           target="_blank"
           class="btn btn-secondary mb-3">
            พิมพ์ใบรับเงิน
        </a>

        <a href="{{ route('technician-payment-batches.index') }}"
           class="btn btn-default mb-3">
            กลับ
        </a>

        @foreach ($groups as $technicianId => $commissions)
            @php
                $technician = $commissions->first()->technician;
                $total = $commissions->sum('commission_amount');
            @endphp

            <div class="card mt-3">
                <div class="card-header bg-light">
                    <strong>ช่าง:</strong> {{ $technician->name ?? '-' }}
                    <span class="float-right">
                        รวม {{ number_format($total, 2) }} บาท
                    </span>
                </div>

                <div class="card-body p-0">
                    <table class="table table-bordered mb-0">
                        <thead>
                            <tr>
                                <th>เลขบิล</th>
                                <th class="text-right">ยอดขาย</th>
                                <th class="text-right">ค่าช่าง</th>
                                <th class="text-right">ปรับเพิ่ม/ลด</th>
                                <th class="text-right">ยอดจ่ายสุทธิ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($commissions as $commission)
                                <tr>
                                    <td>{{ $commission->sale->sale_no ?? '-' }}</td>
                                    <td class="text-right">
                                        {{ number_format($commission->sale_total ?? 0, 2) }}
                                    </td>
                                    <td class="text-right">
                                        {{ number_format($commission->commission_amount ?? 0, 2) }}
                                    </td>
                                    <td class="text-right">
                                        {{ number_format($commission->manual_adjust ?? 0, 2) }}
                                    </td>
                                    <td class="text-right">
                                        {{ number_format($commission->commission_amount ?? 0, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="4" class="text-right">รวม</th>
                                <th class="text-right">{{ number_format($total, 2) }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @endforeach

    </div>
</div>

@endsection
