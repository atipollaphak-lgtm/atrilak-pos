<?php

namespace App\Services;

use App\Models\Sale;

class CommercialDocumentService
{
    /**
     * สร้างข้อมูลเอกสารสำหรับใบขาย V2
     */
    public function buildSaleDocument(
        Sale $sale,
        string $documentType = 'delivery-note'
    ): array {
        $definition = $this->getDocumentDefinition($documentType);

        return [
            'type' => $definition['type'],
            'title' => $definition['title'],
            'short_title' => $definition['short_title'],
            'number' => $sale->sale_no
                ?? 'SALE-' . str_pad(
                    (string) $sale->id,
                    5,
                    '0',
                    STR_PAD_LEFT
                ),
            'date' => $sale->sale_date ?? now(),
            'copy_label' => 'ต้นฉบับ',
            'current_page' => 1,
            'total_pages' => 1,
            'show_tax_information' => $definition['show_tax_information'],
        ];
    }

    /**
     * รายละเอียดมาตรฐานของเอกสารแต่ละประเภท
     */
    private function getDocumentDefinition(string $documentType): array
    {
        $definitions = [
            'delivery-note' => [
                'type' => 'delivery-note',
                'title' => 'ใบส่งของ',
                'short_title' => 'ใบส่งของ',
                'show_tax_information' => false,
            ],

            'tax-invoice' => [
                'type' => 'tax-invoice',
                'title' => 'ใบกำกับภาษี',
                'short_title' => 'ใบกำกับภาษี',
                'show_tax_information' => true,
            ],

            'quotation' => [
                'type' => 'quotation',
                'title' => 'ใบเสนอราคา',
                'short_title' => 'ใบเสนอราคา',
                'show_tax_information' => false,
            ],
        ];
        return $definitions[$documentType]
            ?? $definitions['delivery-note'];
    }
}
