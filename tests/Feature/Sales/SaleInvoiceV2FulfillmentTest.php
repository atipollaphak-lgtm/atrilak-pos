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

    public function test_tax_invoice_reuses_delivery_note_layout_and_adds_tax_customer_rows_when_tax_number_exists(): void
    {
        $sale = Sale::make([
            'sale_no' => 'SAL-TAX-LAYOUT',
            'sale_date' => '2026-07-26',
            'customer_name_snapshot' => 'Snapshot Company',
            'customer_address_snapshot' => 'Snapshot Billing Address',
            'customer_tax_number_snapshot' => 'CUSTOMER-TAX-123',
            'store_tax_number_snapshot' => 'STORE-TAX-123',
            'store_branch_type_snapshot' => 'head_office',
            'delivery_fee' => '0.00',
            'discount' => '0.00',
            'total_amount' => '15.00',
        ]);
        $sale->setRelation('customer', null);
        $sale->setRelation('items', collect());

        $invoice = view('sales.invoice_v2', [
            'sale' => $sale,
            'setting' => null,
            'document' => app(CommercialDocumentService::class)
                ->buildSaleDocument($sale, 'tax-invoice'),
        ])->render();

        $this->assertStringContainsString('class="delivery-note"', $invoice);
        $this->assertStringContainsString('class="delivery-header"', $invoice);
        $this->assertStringContainsString('tax-information-row', $invoice);
        $this->assertStringContainsString('CUSTOMER-TAX-123', $invoice);
        $this->assertStringContainsString('Snapshot Company', $invoice);
        $this->assertStringContainsString('Snapshot Billing Address', $invoice);
        $this->assertStringContainsString('STORE-TAX-123', $invoice);
    }

    public function test_tax_invoice_without_customer_tax_number_has_no_tax_rows_or_store_tax_header(): void
    {
        $sale = Sale::make([
            'sale_no' => 'SAL-NO-TAX',
            'sale_date' => '2026-07-26',
            'store_tax_number_snapshot' => 'STORE-TAX-123',
            'delivery_fee' => '0.00',
            'discount' => '0.00',
            'total_amount' => '15.00',
        ]);
        $sale->setRelation('customer', null);
        $sale->setRelation('items', collect());

        $invoice = view('sales.invoice_v2', [
            'sale' => $sale,
            'setting' => null,
            'document' => app(CommercialDocumentService::class)
                ->buildSaleDocument($sale, 'tax-invoice'),
        ])->render();

        $this->assertStringContainsString('class="delivery-note"', $invoice);
        $this->assertStringNotContainsString('class="tax-information-row"', $invoice);
        $this->assertStringNotContainsString('STORE-TAX-123', $invoice);
    }

    public function test_tax_invoice_preserves_delivery_note_a4_and_a5_paper_layouts(): void
    {
        $sale = Sale::make([
            'sale_no' => 'SAL-TAX-PAPER',
            'sale_date' => '2026-07-26',
            'customer_tax_number_snapshot' => 'CUSTOMER-TAX-123',
            'delivery_fee' => '0.00',
            'discount' => '0.00',
            'total_amount' => '15.00',
        ]);
        $sale->setRelation('customer', null);
        $sale->setRelation('items', collect());
        $document = app(CommercialDocumentService::class)
            ->buildSaleDocument($sale, 'tax-invoice');

        $a4 = view('sales.invoice_v2', compact('sale', 'document') + ['setting' => null])
            ->render();

        $this->app->instance('request', \Illuminate\Http\Request::create(
            '/sales/1/invoice-v2?paper=a5'
        ));
        $a5 = view('sales.invoice_v2', compact('sale', 'document') + ['setting' => null])
            ->render();

        $this->assertStringContainsString('class="invoice paper-a4"', $a4);
        $this->assertStringContainsString('class="delivery-note"', $a4);
        $this->assertStringContainsString('class="invoice paper-a5"', $a5);
        $this->assertStringContainsString('class="delivery-note"', $a5);
        $this->assertStringContainsString('sales-invoice-v2.css', $a4);
        $this->assertStringContainsString('sales-invoice-v2.css', $a5);
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
