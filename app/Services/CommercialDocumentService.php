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
                ?? 'SALE-'.str_pad(
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
            'customer_address' => $this->resolveCustomerAddress($sale),
        ];
    }

    private function resolveCustomerAddress(Sale $sale): string
    {
        $customer = $sale->relationLoaded('customer')
            ? $sale->getRelation('customer')
            : null;
        $selectedDeliveryAddress = $sale->relationLoaded('customerDeliveryAddress')
            ? $sale->getRelation('customerDeliveryAddress')
            : null;
        $deliveryAddresses = $customer?->relationLoaded('deliveryAddresses')
            ? $customer->getRelation('deliveryAddresses')
            : collect();

        if ($selectedDeliveryAddress === null
            && $sale->customer_delivery_address_id !== null) {
            $selectedDeliveryAddress = $deliveryAddresses->firstWhere(
                'id',
                $sale->customer_delivery_address_id
            );
        }

        $defaultDeliveryAddress = $customer?->relationLoaded('defaultDeliveryAddress')
            ? $customer->getRelation('defaultDeliveryAddress')
            : $deliveryAddresses->first(
                fn ($address): bool => (bool) $address->is_default
            );

        $deliverySources = $sale->delivery_type === 'pickup'
            ? []
            : [
                $sale->delivery_full_address_snapshot,
                $selectedDeliveryAddress?->address,
            ];

        foreach (array_merge(
            $deliverySources,
            [
                $defaultDeliveryAddress?->address,
                $sale->customer_address_snapshot,
                $customer?->address,
            ]
        ) as $address) {
            if (is_string($address) && trim($address) !== '') {
                return $address;
            }
        }

        return '-';
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
