@php
    $baseProductUnit = $productUnits->firstWhere('is_base_unit', true);
    $baseUnitName = $baseProductUnit->unit->name ?? 'หน่วยหลัก';
@endphp

<div class="card mt-3">
    <div class="card-header bg-primary d-flex justify-content-between align-items-center">
        <span>รูปแบบการขาย</span>

        <button type="button"
                class="btn btn-sm btn-light"
                data-toggle="modal"
                data-target="#addProductUnitModal">
            + เพิ่มรูปแบบการขาย
        </button>
    </div>

    <div class="card-body">
        @forelse ($productUnits as $productUnit)

            <div class="card mb-3 border">
                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h5 class="mb-1">
                                📦 {{ $productUnit->unit->name ?? '-' }}

                                @if ($productUnit->is_base_unit)
                                    <span class="badge badge-primary">หน่วยหลัก</span>
                                @endif

                                @if (!$productUnit->active)
                                    <span class="badge badge-secondary">ปิดใช้งาน</span>
                                @endif
                            </h5>

                            <div class="text-muted">
                                @if ($productUnit->is_base_unit)
                                    ใช้เป็นหน่วยหลักของสินค้า
                                @else
                                    1 {{ $productUnit->unit->name ?? '-' }}
                                    =
                                    {{ rtrim(rtrim(number_format($productUnit->conversion_rate, 4), '0'), '.') }}
                                    {{ $baseUnitName }}
                                @endif
                            </div>
                        </div>

                        <div>
                            <button type="button"
                                    class="btn btn-warning btn-sm"
                                    data-toggle="modal"
                                    data-target="#editProductUnitModal{{ $productUnit->id }}">
                                <i class="fas fa-edit"></i>
                                แก้ไข
                            </button>

                            @if (!$productUnit->is_base_unit)
                                <form action="{{ route('products.units.destroy', [$product, $productUnit]) }}"
                                      method="POST"
                                      style="display:inline;">
                                    @csrf
                                    @method('DELETE')

                                    <button type="submit"
                                            class="btn btn-danger btn-sm"
                                            onclick="return confirm('ต้องการลบรูปแบบการขายนี้ใช่หรือไม่?')">
                                        <i class="fas fa-trash"></i>
                                        ลบ
                                    </button>
                                </form>
                            @else
                                <button type="button"
                                        class="btn btn-secondary btn-sm"
                                        disabled
                                        title="หน่วยหลักไม่สามารถลบได้">
                                    <i class="fas fa-lock"></i>
                                </button>
                            @endif
                        </div>
                    </div>

                    <hr>

                    @include('products.partials._product_unit_price')

                    @include('products.partials._product_unit_barcodes')

                    @include('products.partials._product_unit_price_tiers')

                    @include('products.partials._product_unit_promotions')

                </div>
            </div>

        @empty
            <div class="text-center text-muted">
                ยังไม่มีรูปแบบการขาย
            </div>
        @endforelse
    </div>
</div>


<div class="modal fade"
     id="addProductUnitModal"
     tabindex="-1"
     role="dialog"
     aria-labelledby="addProductUnitModalLabel"
     aria-hidden="true">

    <div class="modal-dialog" role="document">
        <form method="POST" action="{{ route('products.units.store', $product) }}">
            @csrf

            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addProductUnitModalLabel">
                        เพิ่มรูปแบบการขาย
                    </h5>

                    <button type="button"
                            class="close"
                            data-dismiss="modal"
                            aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body">

                    <div class="form-group">
                        <label>รูปแบบขาย</label>
                        <select name="unit_id" class="form-control" required>
                            <option value="">เลือกหน่วย</option>

                            @foreach ($units as $unit)
                                <option value="{{ $unit->id }}">
                                    {{ $unit->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label>1 หน่วยนี้ เท่ากับกี่ {{ $baseUnitName }}</label>

                        <input type="number"
                               name="conversion_rate"
                               class="form-control"
                               step="0.0001"
                               min="0.0001"
                               value="1"
                               required>

                        <small class="text-muted">
                            เช่น 1 คิว = 50 {{ $baseUnitName }} ให้ใส่ 50
                        </small>
                    </div>

                    <div class="form-group">
                        <label>ราคาซื้อ</label>
                        <input type="number"
                               name="purchase_price"
                               class="form-control"
                               step="0.01"
                               min="0">
                    </div>

                    <div class="form-group">
                        <label>ราคาขาย</label>
                        <input type="number"
                               name="selling_price"
                               class="form-control"
                               step="0.01"
                               min="0">
                    </div>

                    <div class="form-check">
                        <input type="checkbox"
                               name="is_purchase_unit"
                               value="1"
                               class="form-check-input"
                               id="isPurchaseUnit"
                               checked>

                        <label class="form-check-label" for="isPurchaseUnit">
                            ใช้เป็นหน่วยซื้อ
                        </label>
                    </div>

                    <div class="form-check mt-2">
                        <input type="checkbox"
                               name="is_sale_unit"
                               value="1"
                               class="form-check-input"
                               id="isSaleUnit"
                               checked>

                        <label class="form-check-label" for="isSaleUnit">
                            ใช้เป็นหน่วยขาย
                        </label>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button"
                            class="btn btn-secondary"
                            data-dismiss="modal">
                        ยกเลิก
                    </button>

                    <button type="submit" class="btn btn-primary">
                        บันทึกรูปแบบการขาย
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

@include('products.partials._product_barcode_modal')

@include('products.partials._product_unit_edit_modal')
