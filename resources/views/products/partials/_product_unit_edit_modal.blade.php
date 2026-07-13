@foreach ($productUnits as $productUnit)

<div class="modal fade"
     id="editProductUnitModal{{ $productUnit->id }}"
     tabindex="-1"
     role="dialog">

    <div class="modal-dialog" role="document">

        <form method="POST"
              action="{{ route('products.units.update', [$product, $productUnit]) }}">

            @csrf
            @method('PUT')

            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">
                        แก้ไขหน่วยสินค้า
                    </h5>

                    <button type="button"
                            class="close"
                            data-dismiss="modal">

                        <span>&times;</span>

                    </button>
                </div>

                <div class="modal-body">

                    <div class="form-group">

                        <label>หน่วย</label>

                        <input
                            class="form-control"
                            value="{{ $productUnit->unit->name }}"
                            readonly>

                    </div>

                    <div class="form-group">

                        <label>อัตราแปลง</label>

                        <input
                            type="number"
                            name="conversion_rate"
                            class="form-control"
                            step="0.0001"
                            value="{{ $productUnit->conversion_rate }}"
                            required>

                    </div>

                    <div class="form-group">

                        <label>ต้นทุน</label>

                        <input
                            type="number"
                            name="purchase_price"
                            class="form-control"
                            step="0.01"
                            value="{{ $productUnit->purchase_price }}">

                    </div>

                    <div class="form-group">

                        <label>ราคาขาย</label>

                        <input
                            type="number"
                            name="selling_price"
                            class="form-control"
                            step="0.01"
                            value="{{ $productUnit->selling_price }}">

                    </div>

                    <div class="form-check">

                        <input
                            type="checkbox"
                            name="is_purchase_unit"
                            value="1"
                            class="form-check-input"
                            {{ $productUnit->is_purchase_unit ? 'checked' : '' }}>

                        <label class="form-check-label">
                            ใช้เป็นหน่วยซื้อ
                        </label>

                    </div>

                    <div class="form-check mt-2">

                        <input
                            type="checkbox"
                            name="is_sale_unit"
                            value="1"
                            class="form-check-input"
                            {{ $productUnit->is_sale_unit ? 'checked' : '' }}>

                        <label class="form-check-label">
                            ใช้เป็นหน่วยขาย
                        </label>

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
                        class="btn btn-primary">

                        บันทึก

                    </button>

                </div>

            </div>

        </form>

    </div>

</div>

@endforeach
