<div class="modal fade" id="paymentModal" tabindex="-1" role="dialog" aria-labelledby="payment-modal-title"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h4 class="modal-title" id="payment-modal-title">วิธีชำระเงิน</h4>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="ปิด">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <div class="text-center mb-4">
                    <div class="text-muted h5 mb-1">ยอดสุทธิ</div>
                    <div id="payment-total" class="display-4 font-weight-bold text-danger">0.00</div>
                </div>

                <div class="form-group">
                    <label for="payment-method">วิธีชำระเงิน</label>
                    <select id="payment-method" class="form-control form-control-lg">
                        <option value="cash">เงินสด</option>
                        <option value="promptpay">พร้อมเพย์</option>
                        <option value="mixed">เงินสด + พร้อมเพย์</option>
                    </select>
                </div>

                <div id="payment-cash-summary" class="form-group">
                    <label for="payment-cash-amount">เงินสดที่ใช้ชำระ</label>
                    <input id="payment-cash-amount" type="text" class="form-control form-control-lg" value="0.00"
                        readonly>
                </div>

                <div id="payment-mixed-cash-group" class="form-group d-none">
                    <label for="payment-mixed-cash">เงินสดที่ใช้ชำระ</label>
                    <input id="payment-mixed-cash" type="text" inputmode="decimal" autocomplete="off"
                        class="form-control form-control-lg" placeholder="0.00">
                </div>

                <div id="payment-promptpay-group" class="form-group d-none">
                    <label for="payment-promptpay-amount">ยอดพร้อมเพย์</label>
                    <input id="payment-promptpay-amount" type="text" class="form-control form-control-lg"
                        value="0.00" readonly>
                </div>

                <div id="payment-received-group" class="form-group">
                    <label for="payment-received">รับเงินสด</label>
                    <input id="payment-received" type="text" inputmode="decimal" autocomplete="off"
                        class="form-control form-control-lg" placeholder="0.00">
                </div>

                <div class="text-center mt-4">
                    <div class="text-muted h5 mb-1">เงินทอน</div>
                    <div id="payment-change" class="display-4 font-weight-bold text-success">0.00</div>
                </div>

                <div id="payment-error" class="alert alert-danger mt-3 d-none" role="alert"></div>
            </div>

            <div class="modal-footer">
                <button id="btn-cancel-payment" type="button" class="btn btn-secondary btn-lg"
                    data-dismiss="modal">ยกเลิก</button>
                <button id="btn-confirm-payment" type="button" class="btn btn-success btn-lg">
                    ยืนยันการขาย
                </button>
            </div>
        </div>
    </div>
</div>
