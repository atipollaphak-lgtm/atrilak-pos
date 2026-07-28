<div class="card mt-3">
    <div class="card-header bg-primary"><strong>การตั้งราคาสินค้า</strong></div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3"><small class="text-muted">สถานะ</small><div><span class="badge badge-{{ ['pending_review' => 'warning', 'unpriced' => 'danger', 'normal' => 'success', 'inactive' => 'secondary'][$pricingPreview['status']] ?? 'secondary' }}">{{ ['pending_review' => 'รอทบทวน', 'unpriced' => 'ยังไม่ตั้งราคา', 'normal' => 'ปกติ', 'inactive' => 'ไม่ใช้งาน'][$pricingPreview['status']] ?? '-' }}</span></div></div>
            <div class="col-md-3"><small class="text-muted">ต้นทุนเฉลี่ย</small><div>{{ $pricingPreview['average_cost'] ?? '-' }}</div></div>
            <div class="col-md-3"><small class="text-muted">ราคาขายปัจจุบัน</small><div>{{ $pricingPreview['current_price'] ?? '-' }}</div></div>
            <div class="col-md-3"><small class="text-muted">รูปแบบราคา</small><div>{{ $pricingPreview['pricing_method'] ?? '-' }}</div></div>
        </div>
        <div class="mt-3"><a href="{{ route('pricing-management.index') }}" class="btn btn-primary">เปิด Pricing Engine เพื่อดู/แก้ไขราคา</a></div>
    </div>
</div>
