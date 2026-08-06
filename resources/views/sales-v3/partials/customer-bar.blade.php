<section class="pos-v3-customer card">
    <div class="card-body pos-v3-customer-panel">
        <div class="pos-v3-customer-summary-wrap">
            <strong id="v3-customer-summary" class="pos-v3-customer-summary">ลูกค้าทั่วไป</strong>
            <span id="v3-customer-address" class="pos-v3-customer-line">เลือกที่อยู่จัดส่งเพื่อเริ่มคำนวณโซน</span>
        </div>
        <div class="pos-v3-price-zone-control">
            <label for="v3-price-zone-select">โซนราคา</label>
            <select id="v3-price-zone-select" class="form-control form-control-sm" aria-label="โซนราคาตามที่อยู่ลูกค้า" disabled>
                <option value="">รอเลือกที่อยู่</option>
                @foreach ($deliveryZones as $zone)
                    <option value="{{ $zone->id }}" data-zone='@json($zone)'>{{ $zone->name }}</option>
                @endforeach
            </select>
            <span id="v3-zone-status" class="sr-only" role="status" aria-live="polite"></span>
        </div>
        <div class="pos-v3-customer-actions">
            <a id="v3-customer-details" class="btn btn-outline-primary disabled" href="#" target="_blank" rel="noopener" aria-label="ดูข้อมูลลูกค้า" title="ดูข้อมูลลูกค้า" aria-disabled="true" tabindex="-1"><i class="fas fa-eye" aria-hidden="true"></i></a>
            <button id="v3-open-customer-search" type="button" class="btn btn-outline-primary" aria-label="ค้นหาลูกค้า" title="ค้นหาลูกค้า"><i class="fas fa-search" aria-hidden="true"></i></button>
            <button id="v3-open-customer-create-from-bar" type="button" class="btn btn-outline-primary" aria-label="เพิ่มลูกค้า" title="เพิ่มลูกค้า"><i class="fas fa-user-plus" aria-hidden="true"></i></button>
            <button id="v3-clear-customer" type="button" class="btn btn-outline-danger" aria-label="ล้างลูกค้า" title="ล้างลูกค้า"><i class="fas fa-times" aria-hidden="true"></i></button>
        </div>
    </div>
    <div id="v3-address-picker" class="pos-v3-address-picker d-none px-3 pb-3" hidden>
        <label for="v3-address-id" class="small font-weight-bold mb-1">เลือกที่อยู่จัดส่ง</label>
        <select id="v3-address-id" class="form-control" disabled><option value="">เลือกลูกค้าก่อน</option></select>
    </div>
    <div class="d-none">
        <select id="v3-customer-id" class="form-control"><option value="">ลูกค้าทั่วไป</option>@foreach ($customers as $customer)<option value="{{ $customer->id }}" data-name="{{ $customer->name }}" data-phone="{{ $customer->phone }}" data-tax-number="{{ $customer->tax_number ?? '' }}" data-branch-type="{{ $customer->branch_type ?? '' }}" data-branch-number="{{ $customer->branch_number ?? '' }}" data-customer-address="{{ $customer->address ?? '' }}" data-address-count="{{ $customer->delivery_addresses_count }}">{{ $customer->name }}</option>@endforeach</select>
        <select id="v3-technician-id" class="form-control"><option value="">ไม่ระบุ</option>@foreach ($technicians as $technician)<option value="{{ $technician->id }}">{{ $technician->name }}</option>@endforeach</select>
        <input id="v3-pickup" type="checkbox"><span id="v3-address-text"></span><span id="v3-address-zone"></span><span id="v3-address-fee"></span>
    </div>
</section>
