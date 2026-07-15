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
                {{ $sale->customer_name_snapshot !== null
                    ? $sale->customer_name_snapshot
                    : ($sale->customer->name ?? 'ลูกค้าทั่วไป') }}
            </td>

            <td class="info-label">
                โทร
            </td>

            <td style="width: 25%;">
                {{ $sale->customer_phone_snapshot !== null
                    ? $sale->customer_phone_snapshot
                    : ($sale->customer->phone ?? '-') }}
            </td>
        </tr>

                <tr>
            <td class="info-label">
                ที่อยู่
            </td>

            <td colspan="3">
                {{ $sale->customer_address_snapshot !== null
                    ? $sale->customer_address_snapshot
                    : ($sale->customer->address ?? '-') }}
            </td>
        </tr>

        @if ($document['show_tax_information'] ?? false)
            <tr>
                <td class="info-label">
                    เลขประจำตัวผู้เสียภาษี
                </td>

                <td>
                    {{ $sale->customer_tax_number_snapshot !== null
                        ? $sale->customer_tax_number_snapshot
                        : ($sale->customer->tax_number ?? '-') }}
                </td>

                <td class="info-label">
                    สำนักงาน / สาขา
                </td>

                <td>
                    @php
                        $customerBranchType = $sale->customer_branch_type_snapshot !== null
                            ? $sale->customer_branch_type_snapshot
                            : ($sale->customer->branch_type ?? 'สำนักงานใหญ่');
                        $customerBranchNumber = $sale->customer_branch_number_snapshot !== null
                            ? $sale->customer_branch_number_snapshot
                            : ($sale->customer->branch_number ?? null);
                    @endphp
                    @if ($customerBranchType === 'สาขา')
                        สาขา {{ $customerBranchNumber ?? '-' }}
                    @else
                        สำนักงานใหญ่
                    @endif
                </td>
            </tr>
        @endif
    </table>
</div>
