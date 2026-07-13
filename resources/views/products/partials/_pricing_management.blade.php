<div class="card mt-3">

    <div class="card-header bg-primary">
        <strong>
            💰 Pricing Management
        </strong>
    </div>

    <div class="card-body">

        <div class="pricing-dashboard-summary mb-3">

            <div class="row">

                <div class="col-md-3 mb-3">
                    <div class="pricing-metric">
                        <div class="pricing-metric-label">
                            ต้นทุนเฉลี่ย
                        </div>
                        <div class="pricing-metric-value">
                            {{ number_format($pricingPreview['average_cost'] ?? 0, 2) }}
                        </div>
                    </div>
                </div>

                <div class="col-md-3 mb-3">
                    <div class="pricing-metric">
                        <div class="pricing-metric-label">
                            ราคาขายปัจจุบัน
                        </div>
                        <div class="pricing-metric-value">
                            {{ number_format($pricingPreview['old_price'] ?? 0, 2) }}
                        </div>
                    </div>
                </div>

                <div class="col-md-3 mb-3">
                    <div class="pricing-metric">
                        <div class="pricing-metric-label">
                            % กำไรที่ใช้คำนวณ
                        </div>
                        <div class="pricing-metric-value">
                            {{ number_format($pricingPreview['profit_percent'] ?? 0, 2) }}%
                        </div>
                    </div>
                </div>

                <div class="col-md-3 mb-3">
                    <div class="pricing-metric">
                        <div class="pricing-metric-label">
                            ราคาที่ระบบคำนวณได้
                        </div>
                        <div class="pricing-metric-value">
                            {{ number_format($pricingPreview['final_price'] ?? 0, 2) }}
                        </div>
                    </div>
                </div>

            </div>

        </div>

        <div class="row">

            <div class="col-md-4 mb-3">
                <div class="pricing-flow-box">
                    <div class="pricing-flow-label">
                        ราคาก่อนปัด
                    </div>
                    <div class="pricing-flow-value">
                        {{ number_format($pricingPreview['price_before_round'] ?? 0, 2) }}
                    </div>
                </div>
            </div>

            <div class="col-md-4 mb-3">
                <div class="pricing-flow-box">
                    <div class="pricing-flow-label">
                        หลังปัดสตางค์
                    </div>
                    <div class="pricing-flow-value">
                        {{ number_format($pricingPreview['satang_rounded_price'] ?? 0, 2) }}
                    </div>
                </div>
            </div>

            <div class="col-md-4 mb-3">
                <div class="pricing-flow-box">
                    <div class="pricing-flow-label">
                        ราคาหลังปัดบาท
                    </div>
                    <div class="pricing-flow-value">
                        {{ number_format($pricingPreview['final_price'] ?? 0, 2) }}
                    </div>
                </div>
            </div>

        </div>

        @if($pricingPreview['changed'] ?? false)

            <div class="pricing-status-box pricing-status-warning mb-3">
                🟠 มีราคาใหม่ที่คำนวณได้ แต่ยังไม่ได้ Apply
            </div>

        @else

            <div class="pricing-status-box pricing-status-ok mb-3">
                🟢 ราคาปัจจุบันตรงกับผลการคำนวณของระบบ
            </div>

        @endif

        <div class="row">

            <div class="col-md-3 mb-3">
                <label>Auto Pricing</label>
                <input type="text" class="form-control"
                    value="{{ ($pricingPreview['auto_price_enabled'] ?? false) ? 'เปิดใช้งาน' : 'ปิดใช้งาน' }}"
                    readonly>
            </div>

            <div class="col-md-3 mb-3">
                <label>Price Lock</label>
                <input type="text" class="form-control"
                    value="{{ ($pricingPreview['price_lock'] ?? false) ? 'ล็อคราคา' : 'ไม่ล็อคราคา' }}"
                    readonly>
            </div>

            <div class="col-md-3 mb-3">
                <label>วิธีปัดสตางค์</label>
                <input type="text" class="form-control"
                    value="{{ $pricingPreview['satang_rounding_mode'] ?? '-' }}"
                    readonly>
            </div>

            <div class="col-md-3 mb-3">
                <label>วิธีปัดบาท</label>
                <input type="text" class="form-control"
                    value="{{ $pricingPreview['baht_rounding_mode'] ?? '-' }}"
                    readonly>
            </div>

        </div>

    </div>

</div>
