<?php

namespace Tests\Feature\Sales;

use App\Models\Sale;
use App\Services\CommercialDocumentService;
use Tests\TestCase;

class SaleInvoiceV2FulfillmentTest extends TestCase
{
    public function test_invoice_v2_delivery_note_uses_minimal_customer_region_for_pickup(): void
    {
        $invoice = $this->renderInvoice('pickup');

        $this->assertStringContainsString('class="customer-information"', $invoice);
        $this->assertStringNotContainsString('class="delivery-fulfillment"', $invoice);
    }

    public function test_invoice_v2_delivery_note_uses_minimal_customer_region_for_delivery(): void
    {
        $invoice = $this->renderInvoice('delivery');

        $this->assertStringContainsString('class="customer-information"', $invoice);
        $this->assertStringNotContainsString('class="delivery-fulfillment"', $invoice);
    }

    public function test_invoice_v2_does_not_assume_delivery_for_unknown_fulfillment_type(): void
    {
        $invoice = $this->renderInvoice(null);

        $this->assertStringContainsString('class="customer-information"', $invoice);
        $this->assertStringNotContainsString('class="delivery-fulfillment"', $invoice);
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
