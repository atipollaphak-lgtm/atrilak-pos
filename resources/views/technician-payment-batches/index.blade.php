@extends('adminlte::page')

@section('title', 'รอบจ่ายค่าช่าง')

@section('content_header')
    <h1>รอบจ่ายค่าช่าง</h1>
@endsection

@section('content')

    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    <div class="card">

        <div class="card-header d-flex justify-content-between align-items-center">
            <span>รายการรอบจ่ายค่าช่าง</span>

            <a href="{{ route('technician-payment-batches.create') }}" class="btn btn-primary btn-sm">
                + สร้างรอบจ่าย
            </a>
        </div>

        <div class="card-body">

            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>เลขที่รอบจ่าย</th>
                        <th>วันที่จ่าย</th>
                        <th class="text-center">จำนวนช่าง</th>
                        <th class="text-center">จำนวนรายการ</th>
                        <th class="text-right">ยอดรวม</th>
                        <th class="text-center">สถานะ</th>
                        <th class="text-center">จัดการ</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($batches as $batch)
                        <tr>
                            <td>{{ $batch->batch_no }}</td>
                            <td>{{ $batch->payment_date }}</td>
                            <td class="text-center">{{ $batch->total_technicians }}</td>
                            <td class="text-center">{{ $batch->total_items }}</td>
                            <td class="text-right">{{ number_format($batch->total_amount, 2) }}</td>
                            <td class="text-center">
                                @if ($batch->status === 'confirmed')
                                    <span class="badge badge-success">ยืนยันแล้ว</span>
                                @elseif ($batch->status === 'cancelled')
                                    <span class="badge badge-danger">ยกเลิก</span>
                                @else
                                    <span class="badge badge-secondary">{{ $batch->status }}</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <a href="{{ route('technician-payment-batches.show', $batch->id) }}"
                                    class="btn btn-info btn-sm">
                                    ดูรายละเอียด
                                </a>

                                <a href="{{ route('technician-payment-batches.print', $batch->id) }}" target="_blank"
                                    class="btn btn-secondary btn-sm">
                                    พิมพ์
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted">
                                ยังไม่มีรอบจ่ายค่าช่าง
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            {{ $batches->links() }}

        </div>
    </div>

@endsection
