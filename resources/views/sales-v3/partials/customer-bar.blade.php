<section class="pos-v3-customer card">
    <div class="card-body pos-v3-customer-panel">
        <div class="pos-v3-avatar"><i class="fas fa-user"></i></div>
        <div class="flex-grow-1">
            <h4 id="v3-customer-name" class="mb-1">ลูกค้ายังไม่ได้เลือก</h4>
            <div id="v3-customer-phone" class="pos-v3-customer-line"><i class="fas fa-phone"></i> กรุณาเลือกลูกค้า</div>
            <div id="v3-customer-address" class="pos-v3-customer-line"><i class="fas fa-map-marker-alt"></i> เลือกที่อยู่จัดส่งเพื่อเริ่มคำนวณโซน</div>
        </div>
        <div class="pos-v3-customer-actions">
            <button id="v3-open-customer-search" type="button" class="btn btn-outline-success"><i class="fas fa-search"></i>ค้นหาลูกค้า</button>
            <button id="v3-clear-customer" type="button" class="btn btn-outline-danger"><i class="fas fa-trash"></i>ล้างลูกค้า</button>
        </div>
    </div>
    <div class="d-none">
        <select id="v3-customer-id" class="form-control"><option value="">ลูกค้าทั่วไป</option>@foreach ($customers as $customer)<option value="{{ $customer->id }}" data-name="{{ $customer->name }}" data-phone="{{ $customer->phone }}" data-tax-number="{{ $customer->tax_number ?? '' }}">{{ $customer->name }}</option>@endforeach</select>
        <select id="v3-address-id" class="form-control" disabled><option value="">เลือกลูกค้าก่อน</option></select>
        <select id="v3-technician-id" class="form-control"><option value="">ไม่ระบุ</option>@foreach ($technicians as $technician)<option value="{{ $technician->id }}">{{ $technician->name }}</option>@endforeach</select>
        <input id="v3-pickup" type="checkbox"><span id="v3-address-text"></span><span id="v3-address-zone"></span><span id="v3-address-fee"></span>
    </div>
</section>
