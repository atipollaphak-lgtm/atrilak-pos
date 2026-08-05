<div class="modal fade" id="payment-confirmation-modal" tabindex="-1" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content final-payment-modal-content">
            <div class="modal-header">
                <div class="final-payment-heading"><span class="final-payment-icon"><i class="fas fa-baht-sign"></i></span><div><h4 class="modal-title">ยืนยันการชำระเงิน</h4><p>ตรวจสอบรายการสินค้าและยอดรวมก่อนยืนยันการชำระเงิน</p></div></div>
                <button id="final-payment-close" type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="final-payment-columns">
                    <section class="final-bill-card">
                        <div class="final-bill-title"><i class="fas fa-file-invoice"></i><strong>ข้อมูลบิล</strong><strong id="final-preview-sale-no" class="final-bill-number">รอออกเลขที่บิล</strong></div>
                        <div class="final-bill-meta"><div><span>วันที่บิล</span><strong id="final-preview-bill-date">-</strong></div><div><span>ชื่อลูกค้า</span><strong id="final-preview-customer">ลูกค้าทั่วไป</strong></div><div><span>เบอร์โทร</span><strong id="final-preview-phone">-</strong></div><div><span>ที่อยู่</span><strong id="final-preview-address">-</strong></div></div>
                        <div class="final-items-heading"><i class="fas fa-shopping-cart"></i><strong>รายการสินค้า (<span id="final-preview-item-count">0 รายการ</span>)</strong></div>
                        <div class="final-items-table-wrap"><table class="final-items-table"><thead><tr><th>#</th><th>สินค้า</th><th>หน่วย</th><th>จำนวน</th><th>ราคา</th><th>รวม</th></tr></thead><tbody id="final-preview-items"></tbody></table></div>
                        <div class="final-summary-box"><div><span>รวมราคาสินค้า</span><strong id="final-preview-subtotal">0.00</strong></div><div><span>ส่วนลด</span><strong id="final-preview-discount">0.00</strong></div><div><span>ค่าส่ง</span><strong id="final-preview-delivery">0.00</strong></div><hr><div class="final-summary-total"><span>ยอดรวมทั้งสิ้น</span><strong id="final-preview-total">0.00</strong></div></div>
                    </section>
                    <section class="final-payment-side">
                        <div id="final-preview-fulfillment" class="final-fulfillment-card">รับเอง</div>
                        <div class="final-receive-date"><i class="far fa-calendar-alt"></i><span id="final-preview-date-label">วันที่รับสินค้า</span><strong id="final-preview-date">-</strong></div>
                        <div id="final-preview-zone" class="final-preview-zone">-</div>
                        <div id="final-payment-status" class="final-payment-status d-none">ชำระเงินเรียบร้อยแล้ว</div>
                        <div class="final-payable-label">ยอดที่ต้องชำระ</div><div id="final-payable-total" class="final-payable-total">0.00</div><div class="final-baht-label">บาท</div>
                        <div id="final-payment-method-summary" class="final-payment-method-summary"><strong id="final-payment-method-label">วิธีชำระเงิน: ยังไม่ได้ยืนยัน</strong><small id="final-payment-amounts"></small><button id="final-change-payment" type="button" class="btn btn-link btn-sm">เปลี่ยนวิธีชำระเงิน</button></div>
                        <div class="final-payment-notice"><i class="fas fa-info-circle"></i><span>กรุณาตรวจสอบรายการสินค้าและยอดรวมให้ถูกต้อง<br>ก่อนยืนยันการชำระเงิน</span></div>
                        <div class="final-payment-buttons"><button id="final-edit-items" type="button" class="btn btn-outline-success"><i class="fas fa-pen mr-2"></i>แก้ไขรายการ</button><button id="final-confirm-payment" type="button" class="btn btn-success"><i class="fas fa-check mr-2"></i>ยืนยันการชำระเงิน</button></div>
                    </section>
                    <section id="final-document-panel" class="final-document-panel d-none">
                        <h5>เอกสารหลังชำระเงิน</h5><p class="text-muted">เลือกเอกสารที่ต้องการพิมพ์หลังบันทึกการขาย</p>
                        <label class="d-block p-2"><input id="final-print-delivery" type="checkbox" checked> ใบส่งของ</label><label class="d-block p-2"><input id="final-print-tax" type="checkbox"> ใบกำกับภาษี</label><small id="final-tax-help" class="text-muted d-none"></small>
                        <button id="final-print-documents" type="button" class="btn btn-outline-primary btn-block mt-3" disabled><i class="fas fa-print mr-2"></i>พิมพ์เอกสาร</button><button id="final-finish-payment" type="button" class="btn btn-success btn-block mt-2" disabled>✓ เสร็จสิ้น</button>
                    </section>
                </div>
            </div>
        </div>
    </div>
</div>
