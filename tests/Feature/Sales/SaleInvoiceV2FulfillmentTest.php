<?php

namespace Tests\Feature\Sales;

use App\Models\Sale;
use App\Services\CommercialDocumentService;
use Tests\TestCase;

class SaleInvoiceV2FulfillmentTest extends TestCase
{
    public function test_invoice_v2_renders_pickup_fulfillment_label(): void
    {
        $invoice = $this->renderInvoice('pickup');

        $this->assertStringContainsString('🏪 ลูกค้ารับเอง', $invoice);
        $this->assertStringNotContainsString('🚚 จัดส่ง', $invoice);
    }

    public function test_invoice_v2_renders_delivery_fulfillment_label(): void
    {
        $invoice = $this->renderInvoice('delivery');

        $this->assertStringContainsString('🚚 จัดส่ง', $invoice);
        $this->assertStringNotContainsString('🏪 ลูกค้ารับเอง', $invoice);
    }

    public function test_invoice_v2_does_not_assume_delivery_for_unknown_fulfillment_type(): void
    {
        $invoice = $this->renderInvoice(null);

        $this->assertStringNotContainsString('🏪 ลูกค้ารับเอง', $invoice);
        $this->assertStringNotContainsString('🚚 จัดส่ง', $invoice);
    }

    private function renderInvoice(?string $deliveryType): string
    {
        $sale = Sale::make([
            'sale_no' => 'SAL-V2-FULFILLMENT',
            'sale_date' => '2026-07-26',
            'delivery_type' => $deliveryType,
            'delivery_fee' => '0.00',
            'discount' => '0.00',
            'total_amount' => '15.00',
        ]);
        $sale->setRelation('customer', null);
        $sale->setRelation('items', collect());

        return view('sales.invoice_v2', [
            'sale' => $sale,
            'setting' => null,
            'document' => app(CommercialDocumentService::class)
                ->buildSaleDocument($sale, 'delivery-note'),
        ])->render();
    }
}
