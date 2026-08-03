<?php

namespace Tests\Feature\Sales;

use App\Models\Customer;
use App\Models\CustomerDeliveryAddress;
use App\Models\Sale;
use App\Services\CommercialDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

        $this->app->instance('request', Request::create(
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

    public function test_sale_delivery_address_snapshot_is_used_before_live_delivery_relations(): void
    {
        $customer = Customer::make([
            'address' => 'Customer Main Address',
        ]);
        $selectedAddress = CustomerDeliveryAddress::make([
            'address' => 'Selected Live Delivery Address',
            'is_default' => true,
        ]);
        $customer->setRelation('deliveryAddresses', new Collection([$selectedAddress]));

        $sale = Sale::make([
            'sale_no' => 'SAL-ADDRESS-SNAPSHOT',
            'sale_date' => '2026-07-26',
            'delivery_type' => 'delivery',
            'customer_address_snapshot' => 'Billing Snapshot Address',
            'delivery_full_address_snapshot' => 'Sale Delivery Snapshot Address',
            'delivery_fee' => '0.00',
            'discount' => '0.00',
            'total_amount' => '15.00',
        ]);
        $sale->setRelation('customer', $customer);
        $sale->setRelation('customerDeliveryAddress', $selectedAddress);

        $document = app(CommercialDocumentService::class)
            ->buildSaleDocument($sale, 'delivery-note');

        $this->assertSame('Sale Delivery Snapshot Address', $document['customer_address']);

        $html = view('sales.invoice_v2', [
            'sale' => $sale,
            'setting' => null,
            'document' => $document,
        ])->render();

        $this->assertStringContainsString('Sale Delivery Snapshot Address', $html);
        $this->assertStringNotContainsString('Selected Live Delivery Address', $html);
    }

    public function test_sale_document_falls_back_to_customer_default_delivery_address(): void
    {
        $defaultAddress = CustomerDeliveryAddress::make([
            'address' => 'Customer Default Delivery Address',
            'is_default' => true,
        ]);
        $customer = Customer::make([
            'address' => 'Customer Main Address',
        ]);
        $customer->setRelation('deliveryAddresses', new Collection([$defaultAddress]));

        $sale = Sale::make([
            'sale_no' => 'SAL-ADDRESS-DEFAULT',
            'sale_date' => '2026-07-26',
            'delivery_type' => 'delivery',
            'delivery_fee' => '0.00',
            'discount' => '0.00',
            'total_amount' => '15.00',
        ]);
        $sale->setRelation('customer', $customer);
        $sale->setRelation('customerDeliveryAddress', null);

        $document = app(CommercialDocumentService::class)
            ->buildSaleDocument($sale, 'delivery-note');

        $this->assertSame('Customer Default Delivery Address', $document['customer_address']);
    }

    public function test_sale_document_falls_back_to_customer_main_address_for_pickup(): void
    {
        $customer = Customer::make([
            'address' => 'Customer Main Address',
        ]);
        $customer->setRelation('deliveryAddresses', new Collection);

        $sale = Sale::make([
            'sale_no' => 'SAL-ADDRESS-PICKUP',
            'sale_date' => '2026-07-26',
            'delivery_type' => 'pickup',
            'delivery_fee' => '0.00',
            'discount' => '0.00',
            'total_amount' => '15.00',
        ]);
        $sale->setRelation('customer', $customer);
        $sale->setRelation('customerDeliveryAddress', null);

        $document = app(CommercialDocumentService::class)
            ->buildSaleDocument($sale, 'tax-invoice');

        $this->assertSame('Customer Main Address', $document['customer_address']);

        $html = view('sales.invoice_v2', [
            'sale' => $sale,
            'setting' => null,
            'document' => $document,
        ])->render();

        $this->assertStringContainsString('Customer Main Address', $html);
    }

    public function test_sale_document_uses_dash_only_when_no_address_source_exists(): void
    {
        $customer = Customer::make(['address' => null]);
        $customer->setRelation('deliveryAddresses', new Collection);

        $sale = Sale::make([
            'sale_no' => 'SAL-ADDRESS-NONE',
            'sale_date' => '2026-07-26',
            'delivery_type' => 'delivery',
            'delivery_fee' => '0.00',
            'discount' => '0.00',
            'total_amount' => '15.00',
        ]);
        $sale->setRelation('customer', $customer);
        $sale->setRelation('customerDeliveryAddress', null);

        $document = app(CommercialDocumentService::class)
            ->buildSaleDocument($sale, 'delivery-note');

        $this->assertSame('-', $document['customer_address']);
    }

    public function test_tax_invoice_a4_uses_a_compact_blank_row_budget_without_changing_delivery_note(): void
    {
        $sale = Sale::make([
            'sale_no' => 'SAL-TAX-A4-ACCEPTANCE',
            'sale_date' => '2026-07-26',
            'customer_tax_number_snapshot' => 'CUSTOMER-TAX-123',
            'delivery_fee' => '0.00',
            'discount' => '0.00',
            'total_amount' => '15.00',
        ]);
        $sale->setRelation('customer', null);
        $sale->setRelation('items', collect());

        $service = app(CommercialDocumentService::class);
        $taxInvoice = view('sales.invoice_v2', [
            'sale' => $sale,
            'setting' => null,
            'document' => $service->buildSaleDocument($sale, 'tax-invoice'),
        ])->render();
        $deliveryNote = view('sales.invoice_v2', [
            'sale' => $sale,
            'setting' => null,
            'document' => $service->buildSaleDocument($sale, 'delivery-note'),
        ])->render();

        $this->assertStringContainsString('data-document-type="tax-invoice"', $taxInvoice);
        $this->assertSame(12, substr_count($taxInvoice, '<tr><td>&nbsp;</td>'));
        $this->assertSame(15, substr_count($deliveryNote, '<tr><td>&nbsp;</td>'));
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
