<div class="card mb-3">
    <div class="card-header">
        <strong>ปรับราคาทั้งหมวด</strong>
    </div>

    <div class="card-body">
        <form method="POST"
              action="{{ route('pricing-management.apply-category') }}"
              id="categoryPricingForm">

            @csrf

            <div class="row align-items-end">
                <div class="col-md-5">
                    <label>เลือกหมวดสินค้า</label>
                    <select name="category_id"
                            id="categoryPricingCategoryId"
                            class="form-control"
                            required>
                        <option value="">-- เลือกหมวดสินค้า --</option>

                        @foreach ($products->pluck('category')->filter()->unique('id')->sortBy('name') as $category)
                            <option value="{{ $category->id }}">
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <button type="button"
                            class="btn btn-info btn-block"
                            id="categoryPricingPreviewBtn">
                        Preview หมวดนี้
                    </button>
                </div>

                <div class="col-md-3">
                    <button type="submit"
                            class="btn btn-danger btn-block"
                            onclick="return confirm('ยืนยันปรับราคาสินค้าทั้งหมวดนี้หรือไม่?')">
                        Apply ทั้งหมวด
                    </button>
                </div>
            </div>
        </form>

        <div id="categoryPricingPreviewBox" class="mt-3 d-none">
            <div class="alert alert-info mb-2" id="categoryPricingSummary">
                กำลังรอ Preview
            </div>

            <div class="table-responsive">
                <table class="table table-sm table-bordered">
                    <thead>
                        <tr>
                            <th>สินค้า</th>
                            <th class="text-right">ต้นทุน</th>
                            <th class="text-right">ราคาเดิม</th>
                            <th class="text-right">ราคาใหม่</th>
                            <th class="text-center">สถานะ</th>
                        </tr>
                    </thead>
                    <tbody id="categoryPricingPreviewRows">
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
