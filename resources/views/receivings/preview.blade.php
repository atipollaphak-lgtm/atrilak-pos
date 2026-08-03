@extends('adminlte::page')

@section('title', 'ตรวจสอบรับสินค้า')
@section('content_header')<h1>ตรวจสอบรับสินค้า</h1>@stop

@section('content')
    @include('partials.flash-messages')
    <div class="alert alert-warning"><strong>โปรดตรวจสอบ:</strong> การยืนยันจะเพิ่ม Stock และปรับ Average Cost แต่ไม่เปลี่ยน Selling Price หรือ Price Lock</div>
    <div class="card"><div class="card-body">
        <dl class="row"><dt class="col-sm-3">แหล่งที่มา</dt><dd class="col-sm-9">{{ ($preview['source'] ?? '') === 'production' ? 'ผลิตเอง' : 'ซื้อจาก Supplier' }}</dd><dt class="col-sm-3">วันที่รับ</dt><dd class="col-sm-9">{{ $preview['purchase_date'] ?? '-' }}</dd><dt class="col-sm-3">เลขที่เอกสาร</dt><dd class="col-sm-9">{{ $preview['supplier_document_number'] ?? '-' }}</dd></dl>
        <div class="table-responsive"><table class="table table-bordered"><thead><tr><th>สินค้า</th><th>หน่วย</th><th>จำนวน</th><th>ต้นทุน</th><th>รวม</th><th>Stock หลังรับ</th><th>Average Cost หลังรับ</th></tr></thead><tbody>
            @foreach (($preview['lines'] ?? []) as $line)<tr><td>{{ $line['product_name'] }}</td><td>{{ $line['unit_name'] }}</td><td>{{ $line['qty'] }}</td><td>{{ $line['cost_price'] }}</td><td>{{ $line['line_total'] }}</td><td>{{ $line['stock_after'] }}</td><td>{{ $line['average_cost_after'] }}</td></tr>@endforeach
        </tbody><tfoot><tr><th colspan="4" class="text-right">ยอดรวม</th><th>{{ $preview['total_amount'] ?? '0.00' }}</th><th colspan="2"></th></tr></tfoot></table></div>
        <form method="POST" action="{{ route('receivings.confirm') }}" id="receive-confirm-form">
            @csrf
            <input type="hidden" name="preview_token" value="{{ $token }}">
            <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
            <button class="btn btn-success" id="confirm-button">ยืนยันรับสินค้า</button>
            <a href="{{ route('receivings.create') }}" class="btn btn-secondary">กลับไปแก้ไข</a>
        </form>
    </div></div>
@stop

@push('js')<script>document.getElementById('receive-confirm-form')?.addEventListener('submit',function(){document.getElementById('confirm-button').disabled=true;});</script>@endpush
