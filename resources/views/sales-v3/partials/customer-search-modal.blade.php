<div class="modal fade" id="customer-search-modal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="v3-delivery-editor-title" aria-describedby="v3-delivery-editor-help">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div><h4 id="v3-delivery-editor-title" class="modal-title">ลูกค้าและข้อมูลจัดส่ง</h4><p id="v3-delivery-editor-help" class="mb-0 text-muted">เลือกลูกค้าทางซ้าย แล้วเลือกที่อยู่จัดส่งทางขวา</p></div>
                <button type="button" class="close" data-dismiss="modal" aria-label="ปิด">&times;</button>
            </div>
            <div class="modal-body pos-v3-delivery-editor">
                <section class="pos-v3-delivery-customers" aria-labelledby="v3-customer-list-title">
                    <h5 id="v3-customer-list-title">เลือกลูกค้า</h5>
                    <div class="pos-v3-delivery-search"><i class="fas fa-search" aria-hidden="true"></i><input id="v3-customer-search" class="form-control" aria-label="ค้นหาชื่อลูกค้า รหัส หรือเบอร์โทร" placeholder="ค้นหาชื่อ รหัส หรือเบอร์โทร" autocomplete="off"></div>
                    <button id="v3-open-customer-create" type="button" class="btn btn-outline-primary btn-block"><i class="fas fa-user-plus mr-1" aria-hidden="true"></i>เพิ่มลูกค้าใหม่</button>
                    <div class="pos-v3-customer-results">
                        @foreach ($customers as $customer)
                            <article data-customer-row data-customer-id="{{ $customer->id }}" data-address-count="{{ $customer->delivery_addresses_count }}" data-search="{{ strtolower($customer->name.' '.$customer->code.' '.$customer->phone) }}">
                                <button type="button" data-customer-select><strong>{{ $customer->name }}</strong><small>{{ $customer->phone ?: ($customer->code ?: 'ไม่ระบุเบอร์โทร') }}</small><span data-address-count-label>{{ $customer->delivery_addresses_count }} ที่อยู่</span></button>
                                <span data-customer-expand aria-hidden="true"></span>
                            </article>
                            <div class="d-none" data-customer-address-panel><div data-customer-address-list></div></div>
                        @endforeach
                    </div>
                    <div id="v3-customer-empty" class="final-empty d-none">ไม่พบข้อมูลลูกค้า <button id="v3-customer-empty-create" type="button" class="btn btn-link">เพิ่มลูกค้าใหม่</button></div>
                </section>
                <section id="v3-delivery-editor-detail" class="pos-v3-delivery-addresses" tabindex="-1" aria-labelledby="v3-address-list-title" aria-busy="false">
                    <div class="pos-v3-delivery-detail-heading"><div><span>ข้อมูลลูกค้าที่เลือก</span><h5 id="v3-address-list-title">ยังไม่ได้เลือกลูกค้า</h5><small id="v3-delivery-customer-meta">เลือกลูกค้าเพื่อดูที่อยู่จัดส่ง</small></div></div>
                    <div id="v3-delivery-editor-status" class="sr-only" role="status" aria-live="polite"></div>
                    <div id="v3-delivery-address-list" class="pos-v3-address-list"><div class="final-empty">เลือกลูกค้าและที่อยู่</div></div>
                    <div id="v3-address-load-error" class="pos-v3-address-state d-none" role="alert"><strong>โหลดที่อยู่ไม่สำเร็จ</strong><button id="v3-address-retry" type="button" class="btn btn-outline-primary">ลองใหม่</button></div>
                    <div id="v3-no-address" class="pos-v3-address-state d-none"><strong>ลูกค้านี้ยังไม่มีที่อยู่จัดส่ง</strong><a id="v3-manage-customer-addresses" class="btn btn-outline-primary" href="#" target="_blank" rel="noopener">ไปที่ข้อมูลลูกค้า</a></div>
                </section>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal">ยกเลิก</button><button id="v3-delivery-editor-confirm" type="button" class="btn btn-primary">ยืนยันข้อมูลจัดส่ง</button></div>
        </div>
    </div>
</div>
