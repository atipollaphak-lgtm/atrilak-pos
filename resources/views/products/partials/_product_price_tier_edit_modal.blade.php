@foreach ($productUnits as $productUnit)

    @foreach ($productUnit->priceTiers as $tier)

        <div class="modal fade"
             id="editPriceTierModal{{ $tier->id }}"
             tabindex="-1"
             role="dialog">

            <div class="modal-dialog" role="document">

                <form method="POST"
                      action="{{ route('products.price-tiers.update', [$product, $productUnit, $tier]) }}">

                    @csrf
                    @method('PUT')

                    <div class="modal-content">

                        <div class="modal-header">

                            <h5 class="modal-title">
                                แก้ไข Price Tier
                            </h5>

                            <button
                                type="button"
                                class="close"
                                data-dismiss="modal">

                                <span>&times;</span>

                            </button>

                        </div>

                        <div class="modal-body">

                            <div class="form-group">

                                <label>จำนวนขั้นต่ำ</label>

                                <input
                                    type="number"
                                    name="min_qty"
                                    min="1"
                                    class="form-control"
                                    value="{{ $tier->min_qty }}"
                                    required>

                            </div>

                            <div class="form-group">

                                <label>ส่วนลด (%)</label>

                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    name="discount_percent"
                                    class="form-control"
                                    value="{{ $tier->discount_percent }}">

                            </div>

                            <div class="form-group">

                                <label>ราคาพิเศษ</label>

                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    name="fixed_price"
                                    class="form-control"
                                    value="{{ $tier->fixed_price }}">

                            </div>

                            <div class="form-check">

                                <input
                                    type="checkbox"
                                    class="form-check-input"
                                    id="tierActive{{ $tier->id }}"
                                    name="active"
                                    value="1"
                                    {{ $tier->active ? 'checked' : '' }}>

                                <label
                                    class="form-check-label"
                                    for="tierActive{{ $tier->id }}">

                                    เปิดใช้งาน

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

@endforeach
