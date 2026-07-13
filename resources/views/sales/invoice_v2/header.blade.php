@php
    $resolvedDocumentTitle = $document['title']
        ?? 'ใบส่งของ / ใบเสร็จรับเงิน';

    $resolvedDocumentNo = $document['number']
        ?? $sale->sale_no
        ?? (!empty($sale->id)
            ? 'SALE-' . str_pad((string) $sale->id, 5, '0', STR_PAD_LEFT)
            : '-');

    $resolvedDocumentDate = $document['date']
        ?? $sale->sale_date
        ?? now();

    $resolvedCurrentPage = $document['current_page'] ?? 1;
    $resolvedTotalPages = $document['total_pages'] ?? 1;
@endphp

<table class="header-table">
    <tr>
        <td class="header-company-group">
            <table class="header-company-table">
                <tr>
                    @include('sales.invoice_v2.header.company')
                </tr>
            </table>
        </td>

        @include('sales.invoice_v2.header.document')
    </tr>
</table>

<div class="header-divider"></div>
