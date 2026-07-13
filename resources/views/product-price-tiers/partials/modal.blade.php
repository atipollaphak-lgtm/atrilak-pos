<div
    class="modal fade"
    id="priceTierModal"
    tabindex="-1"
    role="dialog"
    aria-labelledby="priceTierModalLabel"
    aria-hidden="true">

    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">

            <form
                id="priceTierForm"
                method="POST"
                action="{{ route('product-price-tiers.store') }}">

                @csrf

                <input
                    type="hidden"
                    name="_method"
                    id="priceTierFormMethod"
                    value="POST">

                <div class="modal-header">
                    <h5 class="modal-title" id="priceTierModalLabel">
                        เพิ่ม Price Tier
                    </h5>

                    <button
                        type="button"
                        class="close"
                        data-dismiss="modal"
                        aria-label="Close">

                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body">

                    <div class="form-group" id="priceTierProductUnitGroup">
                        <label for="priceTierProductUnit">
                            สินค้า / หน่วยขาย
                            <span class="text-danger">*</span>
                        </label>

                        <select
                            name="product_unit_id"
                            id="priceTierProductUnit"
                            class="form-control"
                            required>

                            <option value="">
                                -- เลือกสินค้าและหน่วยขาย --
                            </option>

                            @foreach ($products as $product)
                                @foreach ($product->productUnits as $productUnit)
                                    <option
                                        value="{{ $productUnit->id }}"
                                        data-product-id="{{ $product->id }}"
                                        data-product-name="{{ $product->name }}"
                                        data-unit-name="{{ $productUnit->unit->name ?? $productUnit->unit_name ?? '-' }}"
                                        data-selling-price="{{ $productUnit->selling_price ?? 0 }}">

                                        {{ $product->name }}
                                        —
                                        {{ $productUnit->unit->name ?? $productUnit->unit_name ?? '-' }}
                                        —
                                        {{ number_format($productUnit->selling_price ?? 0, 2) }} บาท
                                    </option>
                                @endforeach
                            @endforeach
                        </select>
                    </div>

                    <div class="row">

                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="priceTierMinQty">
                                    จำนวนขั้นต่ำ
                                    <span class="text-danger">*</span>
                                </label>

                                <input
                                    type="number"
                                    name="min_qty"
                                    id="priceTierMinQty"
                                    class="form-control"
                                    min="1"
                                    step="1"
                                    required>

                                <small class="form-text text-muted">
                                    ตัวอย่าง 10 หมายถึงเริ่มใช้ Tier ตั้งแต่ 10 ชิ้นขึ้นไป
                                </small>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="priceTierType">
                                    รูปแบบราคา
                                    <span class="text-danger">*</span>
                                </label>

                                <select
                                    id="priceTierType"
                                    class="form-control"
                                    required>

                                    <option value="discount">
                                        ลดเป็นเปอร์เซ็นต์
                                    </option>

                                    <option value="fixed">
                                        กำหนดราคาคงที่
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="priceTierActive">
                                    สถานะ
                                </label>

                                <select
                                    name="active"
                                    id="priceTierActive"
                                    class="form-control">

                                    <option value="1">
                                        เปิดใช้งาน
                                    </option>

                                    <option value="0">
                                        ปิดใช้งาน
                                    </option>
                                </select>
                            </div>
                        </div>

                    </div>

                    <div class="row">

                        <div
                            class="col-md-6"
                            id="priceTierDiscountGroup">

                            <div class="form-group">
                                <label for="priceTierDiscountPercent">
                                    ส่วนลด (%)
                                </label>

                                <input
                                    type="number"
                                    name="discount_percent"
                                    id="priceTierDiscountPercent"
                                    class="form-control"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    value="0">
                            </div>
                        </div>

                        <div
                            class="col-md-6 d-none"
                            id="priceTierFixedPriceGroup">

                            <div class="form-group">
                                <label for="priceTierFixedPrice">
                                    ราคาคงที่ต่อหน่วย
                                </label>

                                <input
                                    type="number"
                                    name="fixed_price"
                                    id="priceTierFixedPrice"
                                    class="form-control"
                                    min="0"
                                    step="0.01">
                            </div>
                        </div>

                    </div>

                    <div
                        class="alert alert-info mb-0"
                        id="priceTierPreview">

                        เลือกสินค้า หน่วยขาย และกรอกข้อมูล เพื่อดูตัวอย่างราคา
                    </div>

                </div>

                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-dismiss="modal">

                        ยกเลิก
                    </button>

                    <button
                        type="submit"
                        class="btn btn-primary"
                        id="priceTierSubmitButton">

                        บันทึก Price Tier
                    </button>

                </div>

            </form>

        </div>
    </div>
</div>
