@extends('adminlte::page')

@section('title', 'ประวัติการนำเข้าสมาชิก')

@section('content_header')
    <div class="d-flex align-items-center justify-content-between">
        <h1 class="mb-0">ประวัติการนำเข้าสมาชิก</h1>
        <a href="{{ route('customers.import.index') }}" class="btn btn-primary">นำเข้าไฟล์ใหม่</a>
    </div>
@stop

@section('content')
    <div class="card">
        <div class="card-body table-responsive">
            <table class="table table-hover">
                <thead><tr><th>วันที่</th><th>ไฟล์</th><th>Source</th><th>ผู้ดำเนินการ</th><th>นำเข้า</th><th>ซ้ำ</th><th>ตรวจสอบ</th><th>ผิดพลาด</th><th>สถานะ</th><th></th></tr></thead>
                <tbody>
                    @forelse ($batches as $batch)
                        <tr>
                            <td>{{ $batch->created_at?->format('Y-m-d H:i') }}</td>
                            <td>{{ $batch->original_filename }}</td>
                            <td>{{ $batch->source_system }}</td>
                            <td>{{ $batch->creator?->name ?? '—' }}</td>
                            <td>{{ $batch->imported_count }}</td>
                            <td>{{ $batch->duplicate_count }}</td>
                            <td>{{ $batch->review_count }}</td>
                            <td>{{ $batch->invalid_count }}</td>
                            <td>{{ $batch->status }}</td>
                            <td><a href="{{ route('customers.import.history.show', $batch) }}" class="btn btn-info btn-sm">รายละเอียด</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="text-center">ยังไม่มีประวัติการนำเข้า</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $batches->links() }}
        </div>
    </div>
@stop
