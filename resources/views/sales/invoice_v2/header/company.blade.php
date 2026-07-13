{{-- Logo --}}
<td class="header-logo-cell">
    @php
        $rawLogoPath = $setting->logo_image ?? null;

        $normalizedLogoPath = $rawLogoPath ? ltrim(str_replace('\\', '/', $rawLogoPath), '/') : null;

        $storageLogoPath = $normalizedLogoPath ? preg_replace('#^storage/#', '', $normalizedLogoPath) : null;

        $logoUrl = $storageLogoPath ? asset('storage/' . $storageLogoPath) : null;

        $logoExists = $storageLogoPath ? file_exists(public_path('storage/' . $storageLogoPath)) : false;
    @endphp

    @if ($logoUrl)
        <img src="{{ $logoUrl }}" class="logo" alt="โลโก้ร้าน">
    @endif

</td>

{{-- Store Information --}}
<td class="header-store-cell">
    <div class="store-name">
        {{ $setting->store_name ?? 'อตรีลักษณ์ คอนกรีต' }}
    </div>

    <div class="store-address">
        {{ $setting->store_address ?? 'ที่อยู่ร้าน' }}
    </div>

    <div class="store-contact">
        โทร {{ $setting->store_phone ?? '-' }}
    </div>

    @if (($document['type'] ?? 'delivery-receipt') === 'tax-invoice' && !empty($setting?->tax_number))

    <div class="store-tax-no">
        เลขประจำตัวผู้เสียภาษี {{ $setting->tax_number }}
    </div>

    <div class="store-tax-no">
        @if (($setting->branch_type ?? 'head_office') === 'branch')
            สาขาที่ {{ $setting->branch_number }}
        @else
            สำนักงานใหญ่
        @endif
    </div>

@endif
</td>
