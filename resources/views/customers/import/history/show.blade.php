@extends('adminlte::page')

@section('title', 'รายละเอียด Batch นำเข้าสมาชิก')

@section('content_header')
    <div class="d-flex align-items-center justify-content-between">
        <div>
            <h1 class="mb-1">รายละเอียดการนำเข้าสมาชิก</h1>
            <p class="text-muted mb-0">Batch #{{ $batch->id }} · {{ $batch->original_filename }}</p>
        </div>
        <div>
            <a href="{{ route('customers.import.report', $batch) }}" class="btn btn-outline-danger mr-1">ดาวน์โหลด CSV รายงาน</a>
            <a href="{{ route('customers.import.history') }}" class="btn btn-light">กลับประวัติ</a>
        </div>
    </div>
@stop

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="col-md-3"><strong>Source</strong><div>{{ $batch->source_system }}</div></div>
                <div class="col-md-3"><strong>ผู้ดำเนินการ</strong><div>{{ $batch->creator?->name ?? '—' }}</div></div>
                <div class="col-md-3"><strong>สถานะ</strong><div>{{ $batch->status }}</div></div>
                <div class="col-md-3"><strong>วันที่</strong><div>{{ $batch->created_at?->format('Y-m-d H:i') }}</div></div>
            </div>
            <hr>
            <div class="row text-center">
                <div class="col"><strong>{{ $batch->total_parsed }}</strong><div>ทั้งหมด</div></div>
                <div class="col text-success"><strong>{{ $batch->imported_count }}</strong><div>นำเข้า</div></div>
                <div class="col text-info"><strong>{{ $batch->duplicate_count }}</strong><div>ซ้ำ</div></div>
                <div class="col text-warning"><strong>{{ $batch->review_count }}</strong><div>ตรวจสอบ</div></div>
                <div class="col text-danger"><strong>{{ $batch->invalid_count }}</strong><div>ผิดพลาด</div></div>
                <div class="col text-muted"><strong>{{ $batch->not_selected_count }}</strong><div>ไม่ได้เลือก</div></div>
            </div>
            @if ($batch->failure_reason)
                <div class="alert alert-danger mt-3 mb-0">{{ $batch->failure_reason }}</div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-body table-responsive">
            <table class="table table-sm table-bordered">
                <thead><tr><th>แถว</th><th>External ID</th><th>ชื่อ</th><th>เบอร์โทร</th><th>Tax ID</th><th>สถานะ</th><th>เหตุผล</th></tr></thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>{{ $row->row_number }}</td>
                            <td>{{ $row->external_id ?? '—' }}</td>
                            <td>{{ $row->name ?? '' }}</td>
                            <td>{{ $row->phone ?? '—' }}</td>
                            <td>{{ $row->tax_number ?? '—' }}</td>
                            <td>{{ $row->status }}</td>
                            <td>{{ implode('; ', array_merge($row->reasons ?? [], $row->warnings ?? [])) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center">ไม่พบรายการ</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $rows->links() }}
        </div>
    </div>
@stop
