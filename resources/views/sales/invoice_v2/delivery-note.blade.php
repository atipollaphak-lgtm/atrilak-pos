@php
    $formatNumber = function ($value) {
        $number = (float) $value;

        return floor($number) == $number ? number_format($number, 0) : number_format($number, 2);
    };
    $resolvedDocumentNo = $document['number'] ?? ($sale->sale_no ?? $sale->id);
    $resolvedDocumentDate = $document['date'] ?? ($sale->sale_date ?? now());
    $rawLogoPath = $setting->logo_image ?? null;
    $normalizedLogoPath = $rawLogoPath ? ltrim(str_replace('\\', '/', $rawLogoPath), '/') : null;
    $storageLogoPath = $normalizedLogoPath ? preg_replace('#^storage/#', '', $normalizedLogoPath) : null;
    $logoUrl = $storageLogoPath ? asset('storage/' . $storageLogoPath) : null;

    $storeName = \App\Support\DocumentSnapshotValue::resolve(
        $sale->store_name_snapshot,
        $setting?->store_name,
        'อทรีลักษณ์ คอนกรีต'
    );
    $storeAddress = \App\Support\DocumentSnapshotValue::resolve(
        $sale->store_address_snapshot,
        $setting?->store_address,
        '-'
    );
    $storePhone = \App\Support\DocumentSnapshotValue::resolve(
        $sale->store_phone_snapshot,
        $setting?->store_phone,
        '-'
    );
    $storeTaxNumber = \App\Support\DocumentSnapshotValue::resolve(
        $sale->store_tax_number_snapshot,
        $setting?->tax_number,
        null
    );
    $customerName = \App\Support\DocumentSnapshotValue::resolve(
        $sale->customer_name_snapshot,
        $sale->customer?->name,
        'ลูกค้าทั่วไป'
    );
    $customerPhone = \App\Support\DocumentSnapshotValue::resolve(
        $sale->customer_phone_snapshot,
        $sale->customer?->phone,
        '-'
    );
    $customerAddress = \App\Support\DocumentSnapshotValue::resolve(
        $sale->customer_address_snapshot,
        $sale->customer?->address,
        '-'
    );
    $subTotal = $sale->items->sum('total');
    $deliveryFee = $sale->delivery_fee ?? 0;
    $discount = $sale->discount ?? 0;
    $grandTotal = $subTotal + $deliveryFee - $discount;
    $minimumRows = ($paper ?? 'a4') === 'a5' ? $sale->items->count() : 15;
@endphp

<div class="delivery-note">
    @include('sales.partials.void-document-marker', ['sale' => $sale])

    <header class="delivery-header">
        <div class="company-block">
            <div class="company-brand">
                @if ($logoUrl)
                    <img src="{{ $logoUrl }}" class="delivery-logo" alt="ATRILAK">
                @else
                    <div class="delivery-logo-placeholder">A</div>
                @endif
            </div>
            <div class="company-details">
                <div class="company-name">{{ $storeName }}</div>
                <div class="company-tagline">จำหน่ายวัสดุก่อสร้างทุกชนิด</div>
                <div class="company-line">{{ $storeAddress }}</div>
                <div class="company-line">โทร {{ $storePhone }}</div>
                @if ($storeTaxNumber)
                    <div class="company-line">เลขประจำตัวผู้เสียภาษี {{ $storeTaxNumber }}</div>
                @endif
            </div>
        </div>

        <div class="document-block">
            <h1>ใบส่งของ</h1>
            <div class="document-subtitle">DELIVERY NOTE</div>
            <div class="document-meta">
                <div><span>เลขที่เอกสาร</span><strong>{{ $resolvedDocumentNo }}</strong></div>
                <div><span>วันที่</span><strong>{{ \Carbon\Carbon::parse($resolvedDocumentDate)->format('d/m/Y') }}</strong></div>
            </div>
        </div>
    </header>

    <section class="customer-information">
        <div class="customer-line"><strong>ชื่อลูกค้า :</strong><span>{{ $customerName }}</span><strong>เบอร์โทร :</strong><span>{{ $customerPhone }}</span></div>
        <div class="customer-line"><strong>ที่อยู่ :</strong><span class="customer-address">{{ $customerAddress }}</span></div>
    </section>

    <table class="items-table delivery-note-items">
        <thead>
            <tr>
                <th style="width: 8%;">ลำดับ</th>
                <th style="width: 42%; text-align: left;">รายการสินค้า</th>
                <th style="width: 18%;">จำนวน</th>
                <th style="width: 16%;">หน่วยละ</th>
                <th style="width: 16%;">ราคารวม</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($sale->items as $index => $item)
                @php
                    $itemUnit = \App\Support\DocumentSnapshotValue::resolve(
                        $item->unit_name_snapshot,
                        $item->productUnit?->unit?->name
                            ?? $item->product?->unitRelation?->name
                            ?? $item->product?->unit,
                        ''
                    );
                @endphp
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="item-name">{{ \App\Support\DocumentSnapshotValue::resolve($item->product_name_snapshot, $item->product?->name) }}</td>
                    <td class="text-center">{{ $formatNumber($item->qty) }} {{ $itemUnit }}</td>
                    <td class="text-end">{{ number_format((float) $item->selling_price, 2) }}</td>
                    <td class="text-end">{{ number_format((float) $item->total, 2) }}</td>
                </tr>
            @endforeach
            @for ($row = $sale->items->count(); $row < $minimumRows; $row++)
                <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
            @endfor
        </tbody>
    </table>

    <section class="payment-summary-section">
        <div class="qr-payment">
            @if (!empty($setting?->qr_image))
                <strong>สแกนเพื่อชำระเงิน</strong>
                <span class="promptpay-label">PromptPay</span>
                <img src="{{ asset('storage/' . $setting->qr_image) }}" class="delivery-qr" alt="QR Payment">
                <small>ชื่อบัญชี : {{ $storeName }}</small>
                <small>เลขที่บัญชี : -</small>
            @else
                <span>ช่องทางการชำระเงิน</span>
            @endif
        </div>
        <div class="summary">
            <div><span>รวมเงิน</span><strong>{{ number_format((float) $subTotal, 2) }}</strong></div>
            <div><span>ส่วนลด</span><strong>-{{ number_format((float) $discount, 2) }}</strong></div>
            <div><span>ค่าจัดส่ง</span><strong>{{ number_format((float) $deliveryFee, 2) }}</strong></div>
            <div class="grand-total"><span>ยอดรวมสุทธิ</span><strong>{{ number_format((float) $grandTotal, 2) }}</strong></div>
        </div>
    </section>

    <section class="receiver-section">
        <div class="notes-block"><strong>หมายเหตุ</strong><div>{{ $sale->notes ?: ' ' }}</div><div></div><div></div></div>
        <div class="receiver-block"><strong>ผู้รับสินค้า</strong><div class="signature-line"></div><div>วันที่ ____ / ____ / ______</div></div>
    </section>

    <footer class="delivery-footer">
        <span>ขอบคุณที่ไว้วางใจเรา</span>
        <strong>ATRILAK BUILDING SOLUTIONS</strong>
    </footer>
</div>
