<div class="modal fade" id="v3-quantity-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="v3-quantity-title" class="modal-title">จำนวนสินค้า</h3>
                <button type="button" class="close" data-dismiss="modal" aria-label="ปิด">&times;</button>
            </div>
            <div class="modal-body">
                <div class="v3-quantity-stock-display">
                    <span>สต๊อกหน่วยฐาน</span>
                    <strong id="v3-quantity-stock">0 หน่วย</strong>
                    <small id="v3-quantity-sale-availability">หน่วยขาย -</small>
                </div>
                <div class="v3-quantity-context">
                    <div><span>หน่วยขาย</span><strong id="v3-quantity-unit">-</strong></div>
                    <div><span>ราคาต่อหน่วย</span><strong id="v3-quantity-price">0.00</strong></div>
                    <div><span>รวมรายการ</span><strong id="v3-quantity-total">0.00</strong></div>
                </div>
                <div class="v3-quantity-editor">
                    <button id="v3-quantity-decrease" type="button" class="btn btn-outline-secondary" aria-label="ลดจำนวน">−</button>
                    <input id="v3-quantity-input" class="form-control form-control-lg text-center" type="number" min="0" step="0.01" autocomplete="off" aria-label="จำนวนสินค้า">
                    <button id="v3-quantity-increase" type="button" class="btn btn-outline-secondary" aria-label="เพิ่มจำนวน">+</button>
                </div>
                <div id="v3-quantity-error" class="text-danger mt-2" role="alert"></div>
            </div>
            <div class="modal-footer">
                <button id="v3-quantity-confirm" type="button" class="btn btn-primary btn-lg">เพิ่มสินค้า (Enter)</button>
            </div>
        </div>
    </div>
</div>
