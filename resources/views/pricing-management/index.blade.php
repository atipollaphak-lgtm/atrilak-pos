@extends('adminlte::page')

@section('title', 'ทบทวนราคาขาย')

@section('content_header')
    <h1>ทบทวนราคาขาย</h1>
@stop

@section('content')
    @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    <link rel="stylesheet" href="{{ asset('css/pricing-management.css') }}">

    <div class="row mb-3">
        <div class="col-md-4"><div class="card border-warning"><div class="card-body"><small>รอทบทวน</small><h3>{{ $summary['pending_review'] }}</h3></div></div></div>
        <div class="col-md-4"><div class="card border-danger"><div class="card-body"><small>ยังไม่ตั้งราคา</small><h3>{{ $summary['unpriced'] }}</h3></div></div></div>
        <div class="col-md-4"><div class="card border-success"><div class="card-body"><small>ปกติ</small><h3>{{ $summary['normal'] }}</h3></div></div></div>
    </div>

    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
            <strong>รายการสินค้า</strong>
            <div class="d-flex mt-2 mt-md-0">
                <input id="pricingSearchInput" class="form-control mr-2" placeholder="ค้นหาชื่อหรือรหัสสินค้า">
                <select id="pricingFilterSelect" class="form-control">
                    <option value="">ทุกสถานะ</option>
                    <option value="pending_review">รอทบทวน</option>
                    <option value="unpriced">ยังไม่ตั้งราคา</option>
                    <option value="normal">ปกติ</option>
                    <option value="inactive">ไม่ใช้งาน</option>
                </select>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="pricingTable">
                <thead><tr><th>สถานะ</th><th>สินค้า</th><th class="text-right">ต้นทุนเฉลี่ย</th><th class="text-right">ราคาปัจจุบัน</th><th class="text-right">ราคาแนะนำ</th><th>รูปแบบราคา</th><th class="text-right">กำไร</th><th>จัดการ</th></tr></thead>
                <tbody>
                @foreach ($products as $item)
                    <tr class="pricing-product-row" data-status="{{ $item['status'] }}" data-search="{{ strtolower(($item['product_name'] ?? '').' '.($item['product_id'] ?? '')) }}">
                        <td><span class="badge badge-{{ ['pending_review' => 'warning', 'unpriced' => 'danger', 'normal' => 'success', 'inactive' => 'secondary'][$item['status']] }}">{{ ['pending_review' => 'รอทบทวน', 'unpriced' => 'ยังไม่ตั้งราคา', 'normal' => 'ปกติ', 'inactive' => 'ไม่ใช้งาน'][$item['status']] }}</span></td>
                        <td><strong>{{ $item['product_name'] }}</strong><small class="d-block text-muted">{{ $item['category_name'] ?? '-' }} #{{ $item['product_id'] }}</small></td>
                        <td class="text-right">{{ $item['average_cost'] ?? '-' }}</td>
                        <td class="text-right">{{ $item['current_price'] ?? '-' }}</td>
                        <td class="text-right">{{ $item['status'] === 'normal' || $item['status'] === 'inactive' ? '-' : ($item['suggested_price'] ?? '-') }}</td>
                        <td>{{ ['percentage' => '+'.$item['pricing_value'].'%', 'fixed' => '+'.$item['pricing_value'], 'manual' => 'กำหนดเอง'][$item['pricing_method']] ?? '-' }}</td>
                        <td class="text-right">{{ $item['profit_amount'] === null ? '-' : $item['profit_amount'].' ('.($item['profit_percent'] ?? '-').'%)' }}</td>
                        <td><button type="button" class="btn btn-sm btn-primary js-open-pricing" data-product-id="{{ $item['product_id'] }}">{{ ['pending_review' => 'ตรวจสอบ', 'unpriced' => 'ตั้งราคา', 'normal' => 'ดู/แก้ไข', 'inactive' => 'ดู'][$item['status']] }}</button></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="pricing-drawer" id="pricingDrawer" aria-hidden="true">
        <div class="pricing-drawer-header"><div><h4 id="drawerProductName" class="mb-1"></h4><small id="drawerCategory"></small><div id="drawerStatus"></div></div><button type="button" class="close" id="drawerClose">&times;</button></div>
        <div class="pricing-drawer-body">
            <div id="drawerCost" class="alert alert-light"></div>
            <form id="pricingForm">
                <div class="form-row"><div class="form-group col-7"><label>รูปแบบราคา</label><select name="pricing_method" id="pricingMethod" class="form-control"><option value="percentage">+ เปอร์เซ็นต์</option><option value="fixed">+ จำนวนเงิน</option><option value="manual">กำหนดราคาเอง</option></select></div><div class="form-group col-5"><label id="pricingValueLabel">ค่า</label><div class="input-group"><input name="pricing_value" id="pricingValue" type="number" min="0" step="0.01" class="form-control" required><div class="input-group-append"><span class="input-group-text" id="pricingValueSuffix">%</span></div></div></div></div>
                <div class="form-row" id="roundingRow"><div class="form-group col-7"><label>วิธีการปัด</label><select name="rounding_direction" id="roundingDirection" class="form-control"><option value="up">ปัดขึ้น</option><option value="down">ปัดลง</option><option value="nearest">ใกล้เคียงที่สุด</option></select></div><div class="form-group col-5"><label>หน่วยที่ปัด</label><select name="rounding_unit" id="roundingUnit" class="form-control">@foreach (['0.01','0.05','0.10','0.50','1','5','10','100'] as $unit)<option value="{{ $unit }}">{{ $unit }} บาท</option>@endforeach</select></div></div>
                <div id="drawerResult" class="pricing-result"></div>
                <details class="mt-3"><summary>ดูรายละเอียดการคำนวณ</summary><div id="calculationDetails" class="small text-muted mt-2"></div></details>
                <div id="drawerError" class="alert alert-danger d-none mt-3"></div>
                <div class="d-flex justify-content-end mt-4"><button type="button" class="btn btn-secondary mr-2" id="drawerCancel">ยกเลิก</button><button type="submit" class="btn btn-primary" id="drawerSave">บันทึก</button></div>
            </form>
        </div>
    </div>
@stop

@section('js')
    <script src="{{ asset('js/modules/pricing-management.js') }}"></script>
@stop
