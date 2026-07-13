<div class="customer-section">
    <div class="customer-section-title">
        ข้อมูลลูกค้า
    </div>

    <table class="info-table">
        <tr>
            <td class="info-label">
                ลูกค้า
            </td>

            <td style="width: 45%;">
                {{ $sale->customer->name ?? 'ลูกค้าทั่วไป' }}
            </td>

            <td class="info-label">
                โทร
            </td>

            <td style="width: 25%;">
                {{ $sale->customer->phone ?? '-' }}
            </td>
        </tr>

                <tr>
            <td class="info-label">
                ที่อยู่
            </td>

            <td colspan="3">
                {{ $sale->customer->address ?? '-' }}
            </td>
        </tr>

        @if ($document['show_tax_information'] ?? false)
            <tr>
                <td class="info-label">
                    เลขประจำตัวผู้เสียภาษี
                </td>

                <td>
                    {{ $sale->customer->tax_number ?? '-' }}
                </td>

                <td class="info-label">
                    สำนักงาน / สาขา
                </td>

                <td>
                    @if (($sale->customer->branch_type ?? 'สำนักงานใหญ่') === 'สาขา')
                        สาขา {{ $sale->customer->branch_number ?? '-' }}
                    @else
                        สำนักงานใหญ่
                    @endif
                </td>
            </tr>
        @endif
    </table>
</div>
