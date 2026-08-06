<?php

namespace Tests\Feature\Sales;

use App\Models\Customer;
use App\Models\CustomerDeliveryAddress;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Setting;
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
        $this->assertStringContainsString('style="width: 8%;"', $invoice);
        $this->assertStringContainsString('style="width: 46%; text-align: left;"', $invoice);
        $this->assertStringContainsString('style="width: 15%;"', $invoice);
        $this->assertStringContainsString('style="width: 16%;"', $invoice);
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

    public function test_tax_invoice_uses_customer_invoice_address_instead_of_delivery_address(): void
    {
        $customer = Customer::make([
            'address' => 'Customer Invoice Address',
        ]);
        $deliveryAddress = CustomerDeliveryAddress::make([
            'address' => 'Selected Delivery Site Address',
            'is_default' => true,
        ]);

        $sale = Sale::make([
            'sale_no' => 'SAL-TAX-INVOICE-ADDRESS',
            'sale_date' => '2026-07-26',
            'delivery_type' => 'delivery',
            'customer_address_snapshot' => 'Snapshot Invoice Address',
            'delivery_full_address_snapshot' => 'Snapshot Delivery Address',
            'delivery_fee' => '0.00',
            'discount' => '0.00',
            'total_amount' => '15.00',
        ]);
        $sale->setRelation('customer', $customer);
        $sale->setRelation('customerDeliveryAddress', $deliveryAddress);

        $document = app(CommercialDocumentService::class)
            ->buildSaleDocument($sale, 'tax-invoice');

        $this->assertSame('Snapshot Invoice Address', $document['customer_address']);

        $html = view('sales.invoice_v2', [
            'sale' => $sale,
            'setting' => null,
            'document' => $document,
        ])->render();

        $this->assertStringContainsString('Snapshot Invoice Address', $html);
        $this->assertStringNotContainsString('Snapshot Delivery Address', $html);
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

    public function test_tax_invoice_a4_keeps_production_blank_row_budget_without_changing_delivery_note(): void
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

    public function test_delivery_note_uses_readable_five_column_layout_and_print_hooks(): void
    {
        $longName = str_repeat('Long product name ', 8);
        $sale = Sale::make([
            'sale_no' => 'SAL-LONG-NAME',
            'sale_date' => '2026-07-26',
            'delivery_fee' => '20.00',
            'discount' => '0.00',
            'total_amount' => '32.00',
        ]);
        $sale->setRelation('customer', null);
        $sale->setRelation('items', collect([
            SaleItem::make([
                'qty' => '1.00',
                'selling_price' => '12.00',
                'total' => '12.00',
                'product_name_snapshot' => $longName,
                'unit_name_snapshot' => 'piece',
            ]),
        ]));
        $document = app(CommercialDocumentService::class)
            ->buildSaleDocument($sale, 'delivery-note');

        $html = view('sales.invoice_v2', [
            'sale' => $sale,
            'setting' => new Setting(['qr_image' => 'settings/qr.png']),
            'document' => $document,
        ])->render();
        $css = file_get_contents(base_path('public/css/sales-invoice-v2.css'));

        $this->assertStringContainsString('style="width: 6%;"', $html);
        $this->assertStringContainsString('style="width: 58%; text-align: left;"', $html);
        $this->assertStringContainsString('style="width: 10%;"', $html);
        $this->assertStringContainsString('style="width: 11%;"', $html);
        $this->assertStringContainsString('style="width: 15%;"', $html);
        $this->assertStringContainsString($longName, $html);
        $this->assertStringContainsString('class="delivery-qr"', $html);
        $this->assertStringContainsString(
            '.delivery-note[data-document-type="delivery-note"] .delivery-note-items .item-name',
            $css
        );
        $this->assertStringContainsString('white-space: normal', $css);
        $this->assertStringContainsString(
            ".delivery-note[data-document-type=\"delivery-note\"] .delivery-note-items th,\n.delivery-note[data-document-type=\"delivery-note\"] .delivery-note-items td {\n    font-size: 15px;\n}",
            $css
        );
        $this->assertStringContainsString('font-size: 13px', $css);
        $this->assertStringContainsString(
            '.paper-a5 .delivery-note[data-document-type="delivery-note"] .delivery-note-items',
            $css
        );
    }

    public function test_invoice_stress_counts_keep_one_ten_and_thirty_items_in_the_same_table(): void
    {
        $css = file_get_contents(base_path('public/css/sales-invoice-v2.css'));

        $this->assertNotFalse($css);

        foreach ([1, 2, 3, 10, 30] as $itemCount) {
            $html = $this->renderStressInvoice($itemCount);

            $this->assertSame(
                1 + max($itemCount, 15),
                substr_count($html, '<tr>'),
                "Unexpected row count for {$itemCount} items."
            );
            $this->assertStringContainsString(
                "Long item {$itemCount} that wraps across multiple lines",
                $html
            );
            $this->assertStringNotContainsString('item-cell-content', $html);
        }

        $this->assertStringContainsString('text-overflow: clip', $css);
        $this->assertStringNotContainsString('text-overflow: ellipsis', $css);
    }

    public function test_invoice_footer_handles_qr_states_long_notes_and_multiline_receipt_footer(): void
    {
        $notes = implode("\n", array_map(
            fn (int $line): string => "Note line {$line} with Thai-English content",
            range(1, 10)
        ));
        $footerCases = [
            'ขอบคุณที่ใช้บริการ',
            "ขอบคุณที่ใช้บริการ\nThank you for your purchase\nตรวจสอบสินค้าก่อนออกจากร้าน",
            implode("\n", [
                'ขอบคุณที่ใช้บริการ',
                'Thank you for your purchase',
                'ตรวจสอบสินค้าก่อนออกจากร้าน',
                'Footer line 4',
                'Footer line 5',
            ]),
            implode("\n", array_map(
                fn (int $line): string => "ข้อความท้ายบิลยาวมากสำหรับการทดสอบ {$line} ".str_repeat('Long footer text ', 8),
                range(1, 5)
            )),
        ];

        $withoutQr = $this->renderStressInvoice(1, 'a5', new Setting([
            'receipt_footer' => $footerCases[1],
        ]), $notes);
        $withQr = $this->renderStressInvoice(1, 'a5', new Setting([
            'qr_image' => 'settings/qr.png',
            'receipt_footer' => $footerCases[1],
        ]), $notes);

        $this->assertStringContainsString('class="notes-block"', $withoutQr);
        $this->assertSame(10, substr_count($withoutQr, 'Note line '));
        $this->assertStringContainsString('ยังไม่ได้ตั้งค่า QR Code', $withoutQr);
        $this->assertStringContainsString('class="qr-empty"', $withoutQr);
        $this->assertStringNotContainsString('class="delivery-qr"', $withoutQr);
        $this->assertStringContainsString('class="delivery-qr"', $withQr);
        $this->assertStringContainsString('สแกนเพื่อชำระเงิน', $withQr);
        $this->assertStringContainsString('Thank you for your purchase', $withQr);
        $this->assertStringContainsString('ตรวจสอบสินค้าก่อนออกจากร้าน', $withQr);
        $this->assertStringNotContainsString('class="receiver-section"', $withQr);
        $this->assertStringNotContainsString('ATRILAK BUILDING SOLUTIONS', $withQr);
        $this->assertStringContainsString('ยอดรวมสินค้า', $withoutQr);

        $emptyNotes = $this->renderStressInvoice(1, 'a5', null, null);
        $this->assertStringContainsString('class="notes-content"', $emptyNotes);
        $this->assertStringContainsString('class="notes-empty"', $emptyNotes);
        $this->assertStringContainsString('- ไม่มีหมายเหตุ -', $emptyNotes);

        foreach ($footerCases as $footer) {
            $html = $this->renderStressInvoice(1, 'a5', new Setting([
                'receipt_footer' => $footer,
            ]));

            $this->assertStringContainsString('class="delivery-footer-message"', $html);
            $this->assertStringContainsString(
                e(explode("\n", $footer)[0]),
                $html
            );
        }
    }

    public function test_a5_short_documents_keep_production_row_behavior_without_blank_rows(): void
    {
        $sale = Sale::make([
            'sale_no' => 'SAL-A5-BALANCED',
            'sale_date' => '2026-08-05',
            'customer_tax_number_snapshot' => 'CUSTOMER-TAX-123',
            'delivery_fee' => '0.00',
            'discount' => '0.00',
            'total_amount' => '12.00',
        ]);
        $sale->setRelation('customer', null);
        $sale->setRelation('items', collect([
            SaleItem::make([
                'qty' => '1.00',
                'selling_price' => '12.00',
                'total' => '12.00',
                'product_name_snapshot' => 'Balanced A5 item',
                'unit_name_snapshot' => 'piece',
            ]),
        ]));
        $this->app->instance('request', Request::create(
            '/sales/1/invoice-v2?paper=a5'
        ));

        $service = app(CommercialDocumentService::class);
        $deliveryNote = view('sales.invoice_v2', [
            'sale' => $sale,
            'setting' => null,
            'document' => $service->buildSaleDocument($sale, 'delivery-note'),
        ])->render();
        $taxInvoice = view('sales.invoice_v2', [
            'sale' => $sale,
            'setting' => null,
            'document' => $service->buildSaleDocument($sale, 'tax-invoice'),
        ])->render();

        $this->assertSame(2, substr_count($deliveryNote, '<tr>'));
        $this->assertSame(2, substr_count($taxInvoice, '<tr>'));
    }

    public function test_a5_invoice_keeps_summary_and_footer_in_the_shared_non_signature_layout(): void
    {
        $html = $this->renderStressInvoice(10, 'a5', new Setting([
            'receipt_footer' => implode("\n", [
                'Footer line 1',
                'Footer line 2',
                'Footer line 3',
                'Footer line 4',
                'Footer line 5',
            ]),
        ]));

        $this->assertStringContainsString('class="invoice paper-a5"', $html);
        $this->assertStringContainsString('class="payment-summary-section"', $html);
        $this->assertStringContainsString('class="grand-total"', $html);
        $this->assertStringContainsString('class="delivery-footer"', $html);
        $this->assertStringNotContainsString('class="signature-line"', $html);
        $this->assertStringNotContainsString('class="receiver-section"', $html);
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

    private function renderStressInvoice(
        int $itemCount,
        string $paper = 'a4',
        ?Setting $setting = null,
        ?string $notes = null
    ): string {
        $sale = Sale::make([
            'sale_no' => "SAL-STRESS-{$itemCount}",
            'sale_date' => '2026-07-26',
            'delivery_fee' => '25.00',
            'discount' => '10.00',
            'notes' => $notes,
            'total_amount' => (string) ($itemCount * 12 + 15),
        ]);
        $sale->setRelation('customer', null);
        $sale->setRelation('items', collect(range(1, $itemCount))->map(
            fn (int $index): SaleItem => SaleItem::make([
                'qty' => (string) $index,
                'selling_price' => '12.00',
                'total' => (string) ($index * 12),
                'product_name_snapshot' => $index === 1
                    ? "Long item {$itemCount} that wraps across multiple lines ".str_repeat('ชื่อสินค้ายาว ', 3)
                    : "Stress item {$index}",
                'unit_name_snapshot' => 'piece',
            ])
        ));

        $this->app->instance('request', Request::create(
            "/sales/1/invoice-v2?paper={$paper}"
        ));

        return view('sales.invoice_v2', [
            'sale' => $sale,
            'setting' => $setting,
            'document' => app(CommercialDocumentService::class)
                ->buildSaleDocument($sale, 'delivery-note'),
        ])->render();
    }
}
