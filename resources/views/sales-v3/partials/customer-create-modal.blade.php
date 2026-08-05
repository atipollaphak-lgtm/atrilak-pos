<div class="modal fade" id="v3-customer-create-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">เพิ่มลูกค้าใหม่</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="v3-customer-create-form">
                <div class="modal-body">
                    <div id="v3-customer-create-error" class="alert alert-danger d-none"></div>
                    <div class="form-row">
                        <div class="form-group col-md-7">
                            <label for="v3-new-customer-name">ชื่อลูกค้า <span class="text-danger">*</span></label>
                            <input id="v3-new-customer-name" name="name" class="form-control" required>
                        </div>
                        <div class="form-group col-md-5">
                            <label for="v3-new-customer-phone">เบอร์โทร</label>
                            <input id="v3-new-customer-phone" name="phone" class="form-control" inputmode="tel">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="v3-new-customer-address">ที่อยู่หลัก</label>
                        <textarea id="v3-new-customer-address" name="address" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-7">
                            <label for="v3-new-customer-zone">โซนจัดส่ง</label>
                            <select id="v3-new-customer-zone" name="delivery_zone_id" class="form-control">
                                <option value="">ยังไม่เลือกโซน</option>
                                @foreach ($deliveryZones as $zone)
                                    <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-5">
                            <label for="v3-new-customer-receiver">ชื่อผู้รับ</label>
                            <input id="v3-new-customer-receiver" name="receiver_name" class="form-control">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="v3-new-customer-tax">เลขประจำตัวผู้เสียภาษี</label>
                            <input id="v3-new-customer-tax" name="tax_number" class="form-control">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="v3-new-customer-branch">ประเภทสาขา</label>
                            <select id="v3-new-customer-branch" name="branch_type" class="form-control">
                                <option value="สำนักงานใหญ่">สำนักงานใหญ่</option>
                                <option value="สาขา">สาขา</option>
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="v3-new-customer-branch-number">เลขสาขา</label>
                            <input id="v3-new-customer-branch-number" name="branch_number" class="form-control" maxlength="5" inputmode="numeric">
                        </div>
                    </div>
                    <input type="hidden" name="use_customer_phone" value="1">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">ยกเลิก</button>
                    <button id="v3-customer-create-submit" type="submit" class="btn btn-primary">บันทึกและเลือกลูกค้า</button>
                </div>
            </form>
        </div>
    </div>
</div>
