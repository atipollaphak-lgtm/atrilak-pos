<td class="header-document-cell">

    <div class="doc-header">

        <div class="doc-title">
            {{ $resolvedDocumentTitle }}
        </div>

        <div class="doc-copy-label">
            {{ $document['copy_label'] ?? 'ต้นฉบับ' }}
        </div>

    </div>

    <table class="doc-info-table">
        <tr>
            <td class="doc-info-label">
                เลขที่เอกสาร
            </td>

            <td class="doc-info-value">
                {{ $resolvedDocumentNo }}
            </td>
        </tr>

        <tr>
            <td class="doc-info-label">
                วันที่เอกสาร
            </td>

            <td class="doc-info-value">
                {{ \Carbon\Carbon::parse($resolvedDocumentDate)->format('d/m/Y') }}
            </td>
        </tr>

        <tr>
            <td class="doc-info-label">
                หน้า
            </td>

            <td class="doc-info-value">
                {{ $resolvedCurrentPage }}
                /
                {{ $resolvedTotalPages }}
            </td>
        </tr>
    </table>

</td>
