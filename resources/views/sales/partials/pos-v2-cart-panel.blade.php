<div class="col-lg-4">

    <div class="card pos-cart-panel">

        <div class="card-header pos-cart-header">

            <div class="d-flex align-items-center justify-content-between">

                <div>
                    <i class="fas fa-shopping-cart mr-2"></i>
                    ตะกร้าขาย
                </div>

                <span class="pos-cart-status">
                    พร้อมขาย
                </span>

            </div>

        </div>

        <div class="card-body">

            <div class="pos-cart-table-wrapper">

                <table class="table table-sm table-hover mb-0 pos-cart-table">

                    <thead>

                        <tr>
                            <th width="40%">สินค้า</th>
                            <th class="text-center" width="17%">จำนวน</th>
                            <th class="text-right" width="18%">ราคา</th>
                            <th class="text-right" width="18%">รวม</th>
                            <th class="text-center" width="7%">
                                <i class="fas fa-trash-alt"></i>
                            </th>
                        </tr>

                    </thead>

                    <tbody id="cart-items">

                        <tr>

                            <td colspan="5" class="pos-empty-cart">

                                <div class="pos-empty-cart-icon">
                                    <i class="fas fa-shopping-basket"></i>
                                </div>

                                <div class="pos-empty-cart-title">
                                    ยังไม่มีสินค้า
                                </div>

                                <div class="pos-empty-cart-text">
                                    คลิกสินค้า หรือยิงบาร์โค้ดเพื่อเริ่มขาย
                                </div>

                            </td>

                        </tr>

                    </tbody>

                </table>

            </div>

            <div class="pos-cart-summary">

                <div class="pos-summary-row">

                    <span>
                        ยอดสินค้า
                    </span>

                    <strong>
                        <span id="cart-subtotal">0.00</span>
                        บาท
                    </strong>

                </div>

                <div class="pos-summary-row">

                    <span>
                        ค่าส่ง
                    </span>

                    <strong>
                        <span id="delivery-fee-total">0.00</span>
                        บาท
                    </strong>

                </div>

                <div class="pos-summary-row">

                    <span>
                        ส่วนลด
                    </span>

                    <strong>
                        <span id="discount-total">0.00</span>
                        บาท
                    </strong>

                </div>

            </div>

            <div class="pos-grand-total">

                <div class="pos-grand-total-label">
                    ยอดสุทธิ
                </div>

                <div class="pos-grand-total-value">

                    <span id="cart-total">0.00</span>

                    <small>
                        บาท
                    </small>

                </div>

            </div>

            <button
                id="btn-submit-sale"
                class="btn btn-success btn-lg btn-block"
            >

                <i class="fas fa-check-circle mr-2"></i>

                บันทึกการขาย

            </button>

        </div>

    </div>

</div>
