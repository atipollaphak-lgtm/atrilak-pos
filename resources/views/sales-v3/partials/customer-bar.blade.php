<section class="pos-v3-customer card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-cash-register mr-2"></i>ขายสินค้า</span>
        <span class="small"><span id="pos-v3-clock"></span> · <span id="pos-v3-staff">{{ auth()->user()->name ?? 'พนักงานขาย' }}</span></span>
    </div>
    <div class="card-body">
        <div class="pos-v3-customer-grid">
            <label>ลูกค้า
                <select id="v3-customer-id" class="form-control">
                    <option value="">ลูกค้าทั่วไป</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}">{{ $customer->name }}{{ $customer->phone ? ' · '.$customer->phone : '' }}</option>
                    @endforeach
                </select>
            </label>
            <label>ที่อยู่จัดส่ง
                <select id="v3-address-id" class="form-control" disabled><option value="">เลือกลูกค้าก่อน</option></select>
            </label>
            <label>ช่าง
                <select id="v3-technician-id" class="form-control"><option value="">ไม่ระบุ</option>@foreach ($technicians as $technician)<option value="{{ $technician->id }}">{{ $technician->name }}</option>@endforeach</select>
            </label>
            <label>วันที่ขาย
                <input id="v3-sale-date" class="form-control" type="date" value="{{ now()->toDateString() }}">
            </label>
            <label class="pos-v3-pickup"><span>การจัดส่ง</span><span><input id="v3-pickup" type="checkbox"> รับเอง</span></label>
        </div>
        <div id="v3-address-summary" class="pos-v3-address-summary d-none"><span id="v3-address-text"></span><strong id="v3-address-zone">โซนจัดส่ง: -</strong><strong id="v3-address-fee">ค่าส่งปัจจุบัน 0.00 บาท</strong></div>
    </div>
</section>
