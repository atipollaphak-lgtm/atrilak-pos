<div class="row mb-3">

    <div class="col-md-2">
        <div class="card pricing-summary-card border-left-primary shadow-sm" data-filter="">
            <div class="card-body text-center">
                <div class="text-muted">สินค้าทั้งหมด</div>
                <h3>{{ $summary['total'] }}</h3>
            </div>
        </div>
    </div>

    <div class="col-md-2">
        <div class="card pricing-summary-card border-left-success shadow-sm" data-filter="auto_pricing">
            <div class="card-body text-center">
                <div class="text-muted">Auto Pricing</div>
                <h3>{{ $summary['auto_pricing'] }}</h3>
            </div>
        </div>
    </div>

    <div class="col-md-2">
        <div class="card pricing-summary-card border-left-danger shadow-sm" data-filter="locked">
            <div class="card-body text-center">
                <div class="text-muted">Price Lock</div>
                <h3>{{ $summary['price_lock'] }}</h3>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card pricing-summary-card border-left-warning shadow-sm" data-filter="override">
            <div class="card-body text-center">
                <div class="text-muted">Override</div>
                <h3>{{ $summary['override'] }}</h3>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card pricing-summary-card border-left-info shadow-sm" data-filter="changed">
            <div class="card-body text-center">
                <div class="text-muted">Changed</div>
                <h3>{{ $summary['changed'] }}</h3>
            </div>
        </div>
    </div>

</div>
