@foreach ($productUnits as $productUnit)
    @foreach ($productUnit->barcodes as $barcode)
        <div class="modal fade" id="editBarcodeModal{{ $barcode->id }}" tabindex="-1" role="dialog">

            <div class="modal-dialog" role="document">

                <form method="POST" action="{{ route('products.barcodes.update', [$product, $barcode]) }}">

                    @csrf
                    @method('PUT')

                    <div class="modal-content">

                        <div class="modal-header">

                            <h5 class="modal-title">
                                แก้ไข Barcode
                            </h5>

                            <button type="button" class="close" data-dismiss="modal">
                                <span>&times;</span>
                            </button>

                        </div>

                        <div class="modal-body">

                            <div class="form-group">

                                <label>Barcode</label>

                                <input type="text" name="barcode" class="form-control"
                                    value="{{ $barcode->barcode }}" required>

                            </div>


                            <div class="form-check mb-2">

                                <input type="checkbox" class="form-check-input" name="is_default" value="1"
                                    id="barcodeDefault{{ $barcode->id }}" {{ $barcode->is_default ? 'checked' : '' }}>

                                <label class="form-check-label" for="barcodeDefault{{ $barcode->id }}">

                                    ตั้งเป็น Barcode หลัก

                                </label>

                            </div>
                            <div class="form-check">

                                <input type="checkbox" class="form-check-input" name="active" value="1"
                                    id="barcodeActive{{ $barcode->id }}" {{ $barcode->active ? 'checked' : '' }}>

                                <label class="form-check-label" for="barcodeActive{{ $barcode->id }}">

                                    เปิดใช้งาน

                                </label>

                            </div>

                        </div>

                        <div class="modal-footer">

                            <button type="button" class="btn btn-secondary" data-dismiss="modal">

                                ยกเลิก

                            </button>

                            <button type="submit" class="btn btn-primary">

                                บันทึก

                            </button>

                        </div>

                    </div>

                </form>

            </div>

        </div>
    @endforeach
@endforeach
