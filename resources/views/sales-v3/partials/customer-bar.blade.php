<section class="pos-v3-customer card" aria-label="ข้อมูลจัดส่ง">
    <div class="card-body pos-v3-customer-panel">
        <div class="pos-v3-context-block pos-v3-mode-context">
            <span class="pos-v3-context-label">รูปแบบรับสินค้า</span>
            <div class="pos-v3-cart-controls" role="group" aria-label="รูปแบบรับสินค้า">
                <button id="v3-delivery" type="button" class="btn btn-primary active is-selected" aria-pressed="true"><i class="fas fa-truck" aria-hidden="true"></i><span class="fulfillment-check" aria-hidden="true">✓</span><span class="fulfillment-label">จัดส่ง</span></button>
                <button id="v3-pickup-button" type="button" class="btn btn-outline-primary" aria-pressed="false"><i class="fas fa-store" aria-hidden="true"></i><span class="fulfillment-check" aria-hidden="true" hidden>✓</span><span class="fulfillment-label">รับเอง</span></button>
            </div>
        </div>
        <div class="pos-v3-context-block pos-v3-customer-summary-wrap">
            <span class="pos-v3-context-label">ลูกค้า</span>
            <strong id="v3-customer-summary" class="pos-v3-customer-summary">ลูกค้าทั่วไป</strong>
            <span id="v3-delivery-context-status" class="pos-v3-context-state is-incomplete" role="status" aria-live="polite">ข้อมูลจัดส่งยังไม่ครบ</span>
        </div>
        <div class="pos-v3-context-block pos-v3-address-context">
            <span class="pos-v3-context-label">ที่อยู่และโซนจัดส่ง</span>
            <span id="v3-customer-address" class="pos-v3-customer-line">เลือกลูกค้าและที่อยู่</span>
        </div>
        <div class="pos-v3-context-block pos-v3-price-zone-control">
            <label for="v3-price-zone-select">โซนราคา</label>
            <select id="v3-price-zone-select" class="form-control" aria-label="โซนราคาสำหรับคำนวณราคา">
                <option value="">เลือกโซนราคา</option>
                @foreach ($deliveryZones as $zone)
                    <option value="{{ $zone->id }}" data-zone='@json($zone)'>{{ $zone->name }}</option>
                @endforeach
            </select>
            <span id="v3-zone-status" class="sr-only" role="status" aria-live="polite"></span>
        </div>
        <div class="pos-v3-context-block v3-delivery-date-field">
            <label for="v3-delivery-date-display">วันที่จัดส่ง</label>
            <input id="v3-delivery-date" type="hidden" value="{{ now()->toDateString() }}">
            <input id="v3-delivery-date-display" type="text" value="{{ now()->format('d/m/Y') }}" placeholder="วว/ดด/ปปปป" inputmode="numeric" autocomplete="off" aria-describedby="v3-delivery-date-help">
            <small id="v3-delivery-date-help" class="text-muted">วว/ดด/ปปปป</small>
        </div>
        <div class="pos-v3-customer-actions">
            <button id="v3-open-customer-search" type="button" class="btn btn-primary" aria-label="แก้ไขข้อมูลจัดส่ง" title="แก้ไขข้อมูลจัดส่ง"><i class="fas fa-map-marker-alt" aria-hidden="true"></i><span>แก้ไขข้อมูลจัดส่ง</span></button>
            <a id="v3-customer-details" class="btn btn-outline-primary disabled" href="#" target="_blank" rel="noopener" aria-label="ดูข้อมูลลูกค้า" title="ดูข้อมูลลูกค้า" aria-disabled="true" tabindex="-1"><i class="fas fa-external-link-alt" aria-hidden="true"></i></a>
            <button id="v3-open-customer-create-from-bar" type="button" class="btn btn-outline-primary" aria-label="เพิ่มลูกค้า" title="เพิ่มลูกค้า"><i class="fas fa-user-plus" aria-hidden="true"></i></button>
            <button id="v3-clear-customer" type="button" class="btn btn-outline-danger" aria-label="ล้างลูกค้า" title="ล้างลูกค้า"><i class="fas fa-times" aria-hidden="true"></i></button>
        </div>
    </div>
    <div class="pos-v3-state-adapters d-none">
        <div id="v3-address-picker" class="d-none" hidden>
            <label for="v3-address-id">เลือกที่อยู่จัดส่ง</label>
            <select id="v3-address-id" disabled><option value="">เลือกลูกค้าก่อน</option></select>
        </div>
        <select id="v3-customer-id"><option value="">ลูกค้าทั่วไป</option>@foreach ($customers as $customer)<option value="{{ $customer->id }}" data-name="{{ $customer->name }}" data-phone="{{ $customer->phone }}" data-tax-number="{{ $customer->tax_number ?? '' }}" data-branch-type="{{ $customer->branch_type ?? '' }}" data-branch-number="{{ $customer->branch_number ?? '' }}" data-customer-address="{{ $customer->address ?? '' }}" data-address-count="{{ $customer->delivery_addresses_count }}">{{ $customer->name }}</option>@endforeach</select>
        <select id="v3-technician-id"><option value="">ไม่ระบุ</option>@foreach ($technicians as $technician)<option value="{{ $technician->id }}">{{ $technician->name }}</option>@endforeach</select>
        <input id="v3-pickup" type="checkbox"><span id="v3-address-text"></span><span id="v3-address-zone"></span><span id="v3-address-fee"></span>
    </div>
</section>
