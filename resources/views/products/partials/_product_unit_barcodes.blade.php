<div class="mt-3 p-2 bg-light rounded">

    <div class="d-flex justify-content-between align-items-center mb-2">
        <div class="text-muted small font-weight-bold">
            🏷 Barcode
        </div>

        <button type="button"
                class="btn btn-sm btn-outline-primary"
                data-toggle="modal"
                data-target="#addProductBarcodeModal{{ $productUnit->id }}">
            <i class="fas fa-plus"></i>
            เพิ่ม Barcode
        </button>
    </div>

    @if ($productUnit->barcodes->count())

        @foreach ($productUnit->barcodes->sortBy('sort_order') as $barcode)

            <div class="border rounded px-2 py-1 mb-1 d-flex justify-content-between align-items-center">

                <div>
                    @if ($barcode->is_default)
                        <span class="text-warning mr-1"
                              title="Barcode หลัก">
                            <i class="fas fa-star"></i>
                        </span>
                    @endif

                    <strong>{{ $barcode->barcode }}</strong>

                    @if (!$barcode->active)
                        <span class="badge badge-secondary ml-2">
                            ปิดใช้งาน
                        </span>
                    @endif
                </div>

                <div>
                    <button type="button"
                            class="btn btn-warning btn-sm"
                            data-toggle="modal"
                            data-target="#editBarcodeModal{{ $barcode->id }}">
                        <i class="fas fa-edit"></i>
                    </button>

                    <form action="{{ route('products.barcodes.destroy', [$product, $barcode]) }}"
                          method="POST"
                          style="display:inline;">

                        @csrf
                        @method('DELETE')

                        <button type="submit"
                                class="btn btn-danger btn-sm"
                                onclick="return confirm('ลบ Barcode นี้ใช่หรือไม่?')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                </div>

            </div>

        @endforeach

    @else

        <span class="text-muted">
            ยังไม่มี Barcode
        </span>

    @endif

</div>
