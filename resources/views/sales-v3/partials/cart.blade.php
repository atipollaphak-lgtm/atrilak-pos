<aside class="pos-v3-cart card">
    <div class="pos-v3-cart-date"><label for="v3-sale-date">วันที่จัดส่ง</label><input id="v3-sale-date" type="date" value="{{ now()->toDateString() }}"><button id="v3-clear-cart" type="button" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash-alt mr-1"></i>ล้างตะกร้า</button><button id="v3-new-bill" type="button" class="d-none">บิลใหม่</button></div>
    <div class="pos-v3-cart-controls">
        <button id="v3-delivery" type="button" class="btn btn-outline-success" aria-pressed="false">
            <i class="fas fa-truck mr-2"></i><span class="fulfillment-check" aria-hidden="true" hidden>✓</span><span class="fulfillment-label">จัดส่ง</span>
        </button>
        <button id="v3-pickup-button" type="button" class="btn btn-primary active is-selected" aria-pressed="true">
            <i class="fas fa-store mr-2"></i><span class="fulfillment-check" aria-hidden="true">✓</span><span class="fulfillment-label">รับเอง</span>
        </button>
    </div>
    <div class="v3-cart-table-head"><span>จำนวน</span><span>สินค้า</span><span>ราคาต่อหน่วย</span><span>รวม</span><span aria-hidden="true"></span></div>
    <div id="v3-cart-items" class="pos-v3-cart-items"><div class="pos-v3-empty">ยังไม่มีสินค้า<br><small>คลิกสินค้า หรือยิง Barcode เพื่อเริ่มขาย</small></div></div>
    <div class="pos-v3-cart-footer">
        <div class="d-none"><strong id="v3-subtotal">0.00</strong></div>
        <div class="pos-v3-cart-adjustments"><div><label for="v3-discount">ส่วนลด</label><input id="v3-discount" class="form-control form-control-sm text-right" value="0.00" inputmode="decimal"><span>บาท</span></div><div><label for="v3-delivery-fee">ค่าส่ง</label><input id="v3-delivery-fee" class="form-control form-control-sm text-right" value="0.00" inputmode="decimal"><span>บาท</span></div></div>
        <div class="d-flex justify-content-between align-items-center mt-3 pos-v3-total"><span>ยอดรวมสุทธิ (<span id="v3-cart-count">0</span> รายการ)</span><strong id="v3-total">0.00</strong></div>
        <button id="v3-note-button" type="button" class="d-none">หมายเหตุ</button>
        <button id="v3-submit" type="button" class="btn btn-success btn-lg btn-block mt-2"><i class="fas fa-check mr-1"></i>รับชำระเงิน (Enter)</button>
    </div>
</aside>
