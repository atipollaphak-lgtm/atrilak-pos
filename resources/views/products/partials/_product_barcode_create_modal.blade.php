@foreach ($productUnits as $productUnit)
    <div class="modal fade" id="addProductBarcodeModal{{ $productUnit->id }}" tabindex="-1" role="dialog">

        <div class="modal-dialog" role="document">

            <form method="POST" action="{{ route('products.barcodes.store', $product) }}">

                @csrf

                <input type="hidden" name="product_unit_id" value="{{ $productUnit->id }}">

                <div class="modal-content">

                    <div class="modal-header">

                        <h5 class="modal-title">
                            เพิ่ม Barcode : {{ $productUnit->unit->name }}
                        </h5>

                        <button type="button" class="close" data-dismiss="modal">
                            <span>&times;</span>
                        </button>

                    </div>

                    <div class="modal-body">

                        <div class="form-group">

                            <label>Barcode</label>

                            <input type="text" name="barcode" class="form-control" required>

                        </div>

                    </div>
                    <div class="form-check mt-2">

                        <input type="checkbox" name="is_default" value="1" class="form-check-input"
                            id="newBarcodeDefault{{ $productUnit->id }}">

                        <label class="form-check-label" for="newBarcodeDefault{{ $productUnit->id }}">
                            ตั้งเป็น Barcode หลัก
                        </label>

                    </div>
                    <div class="modal-footer">

                        <button class="btn btn-secondary" data-dismiss="modal" type="button">

                            ยกเลิก

                        </button>

                        <button class="btn btn-primary">

                            บันทึก

                        </button>

                    </div>

                </div>

            </form>

        </div>

    </div>
@endforeach
