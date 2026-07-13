<div class="modal fade"
     id="bulkPreviewModal"
     tabindex="-1"
     aria-hidden="true">

    <div class="modal-dialog modal-lg">

        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">
                    Bulk Pricing Preview
                </h5>

                <button
                    type="button"
                    class="close"
                    data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>

            <div class="modal-body">

                <table class="table table-bordered">

                    <tr>
                        <th>สินค้าทั้งหมด</th>
                        <td id="previewTotal">-</td>
                    </tr>

                    <tr>
                        <th>ราคาเปลี่ยน</th>
                        <td id="previewChanged">-</td>
                    </tr>

                    <tr>
                        <th>Price Lock</th>
                        <td id="previewLocked">-</td>
                    </tr>

                    <tr>
                        <th>Auto Pricing Off</th>
                        <td id="previewAutoOff">-</td>
                    </tr>

                    <tr class="table-success">
                        <th>Apply ได้</th>
                        <td id="previewReady">-</td>
                    </tr>

                </table>

            </div>

            <div class="modal-footer">

                <button
                    type="button"
                    class="btn btn-secondary"
                    data-dismiss="modal">
                    ปิด
                </button>

            </div>

        </div>

    </div>

</div>
