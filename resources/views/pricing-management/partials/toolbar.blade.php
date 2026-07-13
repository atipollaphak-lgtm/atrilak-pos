<div class="row mb-3">
    <div class="col-md-4 mb-2">
        <input type="text" id="pricingSearchInput" class="form-control" placeholder="ค้นหาสินค้า / รหัสสินค้า">
    </div>

    <div class="col-md-3 mb-2">
        <select id="pricingFilterSelect" class="form-control">
            <option value="">แสดงสินค้าทั้งหมด</option>
            <option value="changed">เฉพาะราคาที่เปลี่ยน</option>
            <option value="locked">เฉพาะสินค้าที่ Lock ราคา</option>
            <option value="auto_off">เฉพาะ Auto Pricing Off</option>
            <option value="override">เฉพาะสินค้าที่ Override</option>
        </select>
    </div>

    <div class="col-md-5 mb-2 text-right">

        <form action="{{ route('pricing-management.recalculate-all') }}" method="POST" class="d-inline">
            @csrf

            <button type="submit" class="btn btn-success">
                Recalculate All
            </button>
        </form>

        <button type="button" class="btn btn-info" id="btnBulkPreview">
            Bulk Preview
        </button>

        <form action="{{ route('pricing-management.apply-all') }}" method="POST" class="d-inline">
            @csrf

            <button type="submit" class="btn btn-primary">
                Apply All Changed Prices
            </button>
        </form>

    </div>
</div>
