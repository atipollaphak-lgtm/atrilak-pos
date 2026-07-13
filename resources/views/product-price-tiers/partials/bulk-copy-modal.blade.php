<div
    class="modal fade"
    id="bulkCopyTierModal"
    tabindex="-1"
    role="dialog"
    aria-labelledby="bulkCopyTierModalLabel"
    aria-hidden="true">

    <div class="modal-dialog modal-xl" role="document">

        <div class="modal-content">

            <div class="modal-header">

                <h5
                    class="modal-title"
                    id="bulkCopyTierModalLabel">

                    Bulk Copy Price Tier

                </h5>

                <button
                    type="button"
                    class="close"
                    data-dismiss="modal">

                    <span>&times;</span>

                </button>

            </div>

            <div class="modal-body">

                <div id="bulkCopyContent">

    <div class="row">

        <div class="col-md-4">

            <label>หมวดสินค้า</label>

            <select
                class="form-control"
                id="bulkCopyCategory">

                <option value="">ทั้งหมด</option>

            </select>

        </div>

        <div class="col-md-8">

            <label>ต้นทาง (Source Product Unit)</label>

            <select
                class="form-control"
                id="bulkCopySourceUnit">

                <option value="">
                    -- เลือกต้นทาง --
                </option>

            </select>

        </div>

    </div>

    <hr>

    <div class="d-flex justify-content-between mb-2">

        <div>

            <strong>ปลายทาง</strong>

        </div>

        <div>

            <button
                type="button"
                class="btn btn-sm btn-outline-primary"
                id="bulkCopySelectAll">

                เลือกทั้งหมด

            </button>

            <button
                type="button"
                class="btn btn-sm btn-outline-secondary"
                id="bulkCopyUnselectAll">

                ยกเลิกทั้งหมด

            </button>

        </div>

    </div>

    <div
        id="bulkCopyTargetList"
        class="border rounded p-2"
        style="max-height:320px; overflow:auto;">

        <!-- JS Render Checkbox -->

    </div>

    <div class="mt-3">

        <div class="alert alert-info mb-2">

            เลือกแล้ว

            <strong id="bulkCopyTargetCount">
                0
            </strong>

            รายการ

        </div>

        <div
            id="bulkCopyPreview"
            class="alert alert-secondary mb-0">

            กรุณาเลือกต้นทางและปลายทาง

        </div>

    </div>

</div>

            </div>

            <div class="modal-footer">

                <button
                    type="button"
                    class="btn btn-secondary"
                    data-dismiss="modal">

                    ปิด

                </button>

                <button
                    type="button"
                    class="btn btn-primary"
                    id="bulkCopyTierSubmit">

                    Copy

                </button>

            </div>

        </div>

    </div>

</div>
