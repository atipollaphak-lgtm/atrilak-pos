<div class="modal fade" id="productCostModal"
     data-validation-errors="{{ $errors->hasAny(['current_cost_price', 'cost_price', 'reason']) ? '1' : '0' }}"
     tabindex="-1" role="dialog" aria-labelledby="productCostModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <form id="productCostModalForm" method="POST" action="{{ url('/products') }}" data-action-base="{{ url('/products') }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="product_id" id="productCostProductId" value="{{ old('product_id') }}">
                <input type="hidden" name="search" value="{{ request('search') }}">
                <input type="hidden" name="filter_category_id" value="{{ request('category_id') }}">
                <input type="hidden" name="filter_status" value="{{ request('status') }}">
                <input type="hidden" name="filter_sort" value="{{ request('sort', 'category_name') }}">
                <input type="hidden" name="filter_per_page" value="{{ request('per_page', 50) }}">
                <input type="hidden" name="filter_page" value="{{ request('page') }}">

                <div class="modal-header bg-light">
                    <div>
                        <h5 class="modal-title" id="productCostModalTitle">จัดการต้นทุนสินค้า</h5>
                        <div class="small text-muted" id="productCostModalProductName">เลือกสินค้าจากหน้ารายการ</div>
                    </div>
                    <button type="button" class="close" data-dismiss="modal" aria-label="ปิด"><span aria-hidden="true">&times;</span></button>
                </div>

                <div class="modal-body">
                    <div class="alert alert-warning">
                        <strong>ราคาขายจะไม่เปลี่ยน</strong>
                        <div class="small mt-1">ระบบจะบันทึกประวัติการปรับต้นทุน และให้ Pricing Management ตรวจสอบรายการที่มีสถานะ <code>pending_review</code> ต่อไป</div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label for="productCostCurrent">ต้นทุนปัจจุบัน (Snapshot)</label>
                            <input id="productCostCurrent" name="current_cost_price" type="number" min="0" step="0.01" class="form-control" readonly value="{{ old('current_cost_price') }}">
                            @error('current_cost_price')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="productCostNew">ต้นทุนใหม่</label>
                            <input id="productCostNew" name="cost_price" type="number" min="0" step="0.01" class="form-control" required value="{{ old('cost_price') }}">
                            @error('cost_price')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4"><span class="text-muted d-block">ราคาขายปัจจุบัน</span><strong id="productCostSelling">—</strong></div>
                        <div class="col-md-4"><span class="text-muted d-block">กำไรก่อนปรับ</span><strong id="productCostProfitBefore">—</strong></div>
                        <div class="col-md-4"><span class="text-muted d-block">กำไรหลังปรับ</span><strong id="productCostProfitAfter">—</strong></div>
                    </div>

                    <div class="form-group mt-4">
                        <label for="productCostReason">เหตุผลการปรับต้นทุน</label>
                        <textarea id="productCostReason" name="reason" rows="3" maxlength="500" class="form-control" required>{{ old('reason') }}</textarea>
                        @error('reason')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-warning">บันทึกการปรับต้นทุน</button>
                </div>
            </form>
        </div>
    </div>
</div>
