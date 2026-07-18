<div class="modal fade" id="voidSaleModal{{ $sale->id }}" tabindex="-1"
    aria-labelledby="voidSaleModalLabel{{ $sale->id }}" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('sales.void', $sale) }}" class="modal-content" data-void-sale-form>
            @csrf

            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="voidSaleModalLabel{{ $sale->id }}">ยืนยันการยกเลิกบิล</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="ปิด">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <p class="mb-3">
                    บิล <strong>{{ $sale->sale_no }}</strong> จะถูกยกเลิก สต็อกจะถูกคืน
                    และบิลจะเก็บไว้เป็นหลักฐานทางประวัติศาสตร์
                    <strong>ไม่สามารถย้อนกลับได้</strong>
                </p>

                <div class="form-group mb-0">
                    <label for="void_reason_{{ $sale->id }}">เหตุผลการยกเลิก</label>
                    <textarea id="void_reason_{{ $sale->id }}" name="void_reason" class="form-control" rows="3"
                        maxlength="1000" required>{{ old('void_reason') }}</textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">ยกเลิก</button>
                <button type="submit" class="btn btn-danger">ยืนยันยกเลิกบิล</button>
            </div>
        </form>
    </div>
</div>
