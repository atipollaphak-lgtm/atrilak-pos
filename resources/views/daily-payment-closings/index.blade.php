@extends('adminlte::page')

@section('title', 'ปิดยอดประจำวัน')

@section('content_header')
    <h1>ปิดยอดประจำวัน</h1>
@endsection

@section('content')
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>ประวัติการปิดยอด</span>
            <a href="{{ route('daily-payment-closings.create') }}" class="btn btn-primary btn-sm">
                เปิดปิดยอดวันนี้
            </a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-striped mb-0">
                    <thead>
                        <tr>
                            <th>วันที่</th>
                            <th class="text-center">สถานะ</th>
                            <th class="text-right">เงินสดคาดหวัง</th>
                            <th class="text-right">เงินสดจริง</th>
                            <th class="text-right">ผลต่างเงินสด</th>
                            <th class="text-right">PromptPay คาดหวัง</th>
                            <th class="text-right">PromptPay จริง</th>
                            <th class="text-right">ผลต่าง PromptPay</th>
                            <th>ผู้ปิดยอด / เวลา</th>
                            <th class="text-center">Revision</th>
                            <th class="text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($closings as $closing)
                            <tr>
                                <td>{{ $closing->business_date }}</td>
                                <td class="text-center">
                                    <span class="badge badge-{{ $closing->status === 'finalized' ? 'success' : 'warning' }}">
                                        {{ $closing->status }}
                                    </span>
                                </td>
                                <td class="text-right">{{ number_format($closing->expected_cash_amount, 2) }}</td>
                                <td class="text-right">{{ number_format($closing->actual_cash_amount, 2) }}</td>
                                <td class="text-right {{ $closing->cash_variance != 0 ? 'text-warning font-weight-bold' : '' }}">{{ number_format($closing->cash_variance, 2) }}</td>
                                <td class="text-right">{{ number_format($closing->expected_promptpay_amount, 2) }}</td>
                                <td class="text-right">{{ number_format($closing->actual_promptpay_amount, 2) }}</td>
                                <td class="text-right {{ $closing->promptpay_variance != 0 ? 'text-warning font-weight-bold' : '' }}">{{ number_format($closing->promptpay_variance, 2) }}</td>
                                <td>
                                    @if ($closing->finalizedBy)
                                        {{ $closing->finalizedBy->name }}<br>
                                        <small>{{ optional($closing->finalized_at)->format('d/m/Y H:i') }}</small>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="text-center">{{ $closing->revision }}</td>
                                <td class="text-center text-nowrap">
                                    @if ($closing->status === 'open')
                                        <a href="{{ route('daily-payment-closings.edit', $closing) }}" class="btn btn-primary btn-sm">แก้ไข</a>
                                    @endif
                                    <a href="{{ route('daily-payment-closings.show', $closing) }}" class="btn btn-info btn-sm">รายละเอียด</a>
                                    @if ($closing->status === 'finalized')
                                        <a href="{{ route('daily-payment-closings.print', $closing) }}" target="_blank" class="btn btn-secondary btn-sm">พิมพ์</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="text-center text-muted py-4">ยังไม่มีรายการปิดยอดประจำวัน</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($closings->hasPages())
            <div class="card-footer">{{ $closings->links() }}</div>
        @endif
    </div>
@endsection
