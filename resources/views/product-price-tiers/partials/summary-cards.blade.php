<div class="row">
    <div class="col-md-3">
        <div class="small-box bg-info">
            <div class="inner">
                <h3>{{ number_format($summary['products'] ?? 0) }}</h3>
                <p>สินค้าทั้งหมด</p>
            </div>
            <div class="icon">
                <i class="fas fa-box"></i>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="small-box bg-success">
            <div class="inner">
                <h3>{{ number_format($summary['product_units'] ?? 0) }}</h3>
                <p>หน่วยขายทั้งหมด</p>
            </div>
            <div class="icon">
                <i class="fas fa-balance-scale"></i>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="small-box bg-warning">
            <div class="inner">
                <h3>{{ number_format($summary['price_tiers'] ?? 0) }}</h3>
                <p>Price Tier ทั้งหมด</p>
            </div>
            <div class="icon">
                <i class="fas fa-layer-group"></i>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="small-box bg-secondary">
            <div class="inner">
                <h3>{{ number_format($summary['active_price_tiers'] ?? 0) }}</h3>
                <p>Tier ที่เปิดใช้งาน</p>
            </div>
            <div class="icon">
                <i class="fas fa-check-circle"></i>
            </div>
        </div>
    </div>
</div>
