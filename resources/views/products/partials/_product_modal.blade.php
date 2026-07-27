<div class="modal fade" id="productModal" data-validation-errors="{{ $errors->any() ? '1' : '0' }}" data-old-product-id="{{ old('product_id') }}" tabindex="-1" role="dialog" aria-labelledby="productModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content product-modal">
            <form id="productModalForm" method="POST" action="{{ route('products.store') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="_method" id="productModalMethod" value="POST">
                <input type="hidden" name="product_id" id="productModalId" value="{{ old('product_id') }}">
                <input type="hidden" name="search" value="{{ request('search') }}">
                <input type="hidden" name="filter_category_id" value="{{ request('category_id') }}">
                <input type="hidden" name="filter_status" value="{{ request('status') }}">
                <input type="hidden" name="filter_sort" value="{{ request('sort', 'category_name') }}">
                <input type="hidden" name="filter_per_page" value="{{ request('per_page', 50) }}">
                <input type="hidden" name="filter_page" value="{{ request('page') }}">

                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="productModalTitle">เพิ่มสินค้า</h5>
                        <div class="small text-muted" id="productModalSubtitle">ข้อมูลพื้นฐานและราคาเริ่มต้น</div>
                    </div>
                    <button type="button" class="close" data-dismiss="modal" aria-label="ปิด"><span aria-hidden="true">&times;</span></button>
                </div>

                <div class="modal-body">
                    <div class="row">
                        <div class="col-lg-4 mb-4">
                            <div class="product-image-upload text-center">
                                <img id="productModalImagePreview" class="product-modal-image" src="{{ asset('images/product-placeholder.svg') }}" alt="ตัวอย่างรูปสินค้า">
                                <label class="btn btn-outline-secondary btn-sm mt-3 mb-0">
                                    <i class="fas fa-camera mr-1"></i> เลือกรูปสินค้า
                                    <input type="file" name="image" id="productModalImage" accept="image/jpeg,image/png,image/webp" class="d-none">
                                </label>
                            </div>
                        </div>
                        <div class="col-lg-8">
                            <div class="row">
                                <div class="col-md-6 form-group"><label for="productModalName">ชื่อสินค้า</label><input id="productModalName" name="name" value="{{ old('name') }}" class="form-control" required>@error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
                                <div class="col-md-6 form-group"><label for="productModalCode">รหัสสินค้า</label><input id="productModalCode" name="product_code" value="{{ old('product_code') }}" class="form-control" readonly placeholder="ระบบจะสร้างอัตโนมัติ"><small class="form-text text-muted" id="productModalCodeHint">ระบบจะสร้างอัตโนมัติ</small></div>
                                <div class="col-md-6 form-group"><label for="productModalCategory">หมวดหมู่</label><select id="productModalCategory" name="category_id" class="form-control" required><option value="">เลือกหมวดหมู่</option>@foreach ($categories as $category)<option value="{{ $category->id }}" data-code-prefix="{{ $category->code_prefix }}" data-barcode-prefix="{{ $category->barcode_prefix }}" @selected((string) old('category_id') === (string) $category->id)>{{ $category->name }}</option>@endforeach</select>@error('category_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
                                <div class="col-md-6 form-group"><label for="productModalUnit">หน่วยหลัก</label><select id="productModalUnit" name="unit_id" class="form-control"><option value="">ยังไม่ได้เลือก</option>@foreach ($units as $unit)<option value="{{ $unit->id }}" @selected((string) old('unit_id') === (string) $unit->id)>{{ $unit->name }}</option>@endforeach</select>@error('unit_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
                                <div class="col-md-6 form-group"><label for="productModalBarcode">Barcode</label><input id="productModalBarcode" name="barcode" value="{{ old('barcode') }}" class="form-control" readonly placeholder="ระบบจะสร้าง EAN-13 อัตโนมัติ"><small class="form-text text-muted" id="productModalBarcodeHint">ระบบจะสร้าง EAN-13 อัตโนมัติ</small></div>
                                <div class="col-md-6 form-group"><label for="productModalActive">สถานะ</label><select id="productModalActive" name="active" class="form-control"><option value="1" @selected((string) old('active', '1') === '1')>เปิดใช้งาน</option><option value="0" @selected((string) old('active') === '0')>ปิดใช้งาน</option></select></div>
                                <div class="col-12 form-group"><label for="productModalRemark">หมายเหตุ</label><textarea id="productModalRemark" name="remark" rows="3" class="form-control">{{ old('remark') }}</textarea>@error('remark')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
                            </div>
                        </div>
                    </div>

                    <div class="product-modal-section" id="productInitialPriceSection">
                        <h6>ราคาเริ่มต้น</h6>
                        <div class="row">
                            <div class="col-md-6 form-group"><label for="productModalCost">ต้นทุนเริ่มต้น</label><input id="productModalCost" name="cost_price" value="{{ old('cost_price') }}" type="number" min="0" step="0.01" class="form-control">@error('cost_price')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
                            <div class="col-md-6 form-group"><label for="productModalSelling">ราคาขายเริ่มต้น</label><input id="productModalSelling" name="selling_price" value="{{ old('selling_price') }}" type="number" min="0" step="0.01" class="form-control">@error('selling_price')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
                        </div>
                    </div>

                    <div class="product-modal-section d-none" id="productReadOnlySection">
                        <h6>ข้อมูลสรุป (อ่านอย่างเดียว)</h6>
                        <div class="row">
                            <div class="col-md-3"><span class="summary-label">ต้นทุนเฉลี่ย</span><strong id="productReadOnlyCost">—</strong></div>
                            <div class="col-md-3"><span class="summary-label">ราคาขายปัจจุบัน</span><strong id="productReadOnlySelling">—</strong></div>
                            <div class="col-md-3"><span class="summary-label">กำไร %</span><strong id="productReadOnlyProfit">—</strong></div>
                            <div class="col-md-3"><span class="summary-label">คงเหลือปัจจุบัน</span><strong id="productReadOnlyStock">—</strong></div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-6"><span class="summary-label">มูลค่าสต็อก</span><strong id="productReadOnlyStockValue">—</strong></div>
                            <div class="col-md-6"><span class="summary-label">กฎการขาย</span><select class="form-control" disabled aria-label="กฎการขายในรายละเอียด"><option>ยังไม่ได้กำหนด</option></select></div>
                        </div>
                        <div class="small text-muted mt-3" id="productUsageSummary"></div>
                        <div class="mt-3">
                            <button type="button" class="btn btn-outline-secondary btn-sm" disabled>จัดการราคา</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" disabled>ดูประวัติสต็อก</button>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary" id="productModalSubmit">บันทึก</button>
                </div>
            </form>
        </div>
    </div>
</div>
