{{-- Logo --}}
<td class="header-logo-cell">
    @php
        $rawLogoPath = $setting->logo_image ?? null;

        $normalizedLogoPath = $rawLogoPath ? ltrim(str_replace('\\', '/', $rawLogoPath), '/') : null;

        $storageLogoPath = $normalizedLogoPath ? preg_replace('#^storage/#', '', $normalizedLogoPath) : null;

        $logoUrl = $storageLogoPath ? asset('storage/' . $storageLogoPath) : null;

        $logoExists = $storageLogoPath ? file_exists(public_path('storage/' . $storageLogoPath)) : false;

        $storeName = \App\Support\DocumentSnapshotValue::resolve(
            $sale->store_name_snapshot,
            $setting?->store_name,
            'อตรีลักษณ์ คอนกรีต'
        );
        $storeAddress = \App\Support\DocumentSnapshotValue::resolve(
            $sale->store_address_snapshot,
            $setting?->store_address,
            'ที่อยู่ร้าน'
        );
        $storePhone = \App\Support\DocumentSnapshotValue::resolve(
            $sale->store_phone_snapshot,
            $setting?->store_phone
        );
        $storeTaxNumber = \App\Support\DocumentSnapshotValue::resolve(
            $sale->store_tax_number_snapshot,
            $setting?->tax_number,
            null
        );
        $storeBranchType = \App\Support\DocumentSnapshotValue::resolve(
            $sale->store_branch_type_snapshot,
            $setting?->branch_type,
            'head_office'
        );
        $storeBranchNumber = \App\Support\DocumentSnapshotValue::resolve(
            $sale->store_branch_number_snapshot,
            $setting?->branch_number,
            null
        );
    @endphp

    @if ($logoUrl)
        <img src="{{ $logoUrl }}" class="logo" alt="โลโก้ร้าน">
    @endif

</td>

{{-- Store Information --}}
<td class="header-store-cell">
    <div class="store-name">
        {{ $storeName }}
    </div>

    <div class="store-address">
        {{ $storeAddress }}
    </div>

    <div class="store-contact">
        โทร {{ $storePhone }}
    </div>

    @if (($document['type'] ?? 'delivery-receipt') === 'tax-invoice'
        && $storeTaxNumber !== null && $storeTaxNumber !== '')

    <div class="store-tax-no">
        เลขประจำตัวผู้เสียภาษี {{ $storeTaxNumber }}
    </div>

    <div class="store-tax-no">
        @if ($storeBranchType === 'branch')
            สาขาที่ {{ $storeBranchNumber ?? '-' }}
        @else
            สำนักงานใหญ่
        @endif
    </div>

@endif
</td>
