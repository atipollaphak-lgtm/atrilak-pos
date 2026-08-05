<?php

namespace Tests\Feature\Sales;

use App\Http\Controllers\SaleController;
use App\Models\Sale;
use App\Services\CommercialDocumentService;
use App\Services\Sales\SaleFinancialSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class SalePaymentDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_detail_and_history_render_payment_labels_without_raw_enum_values(): void
    {
        $cash = $this->sale('cash', '100.00', '0.00', '150.00', '50.00');
        $promptpay = $this->sale('promptpay', '0.00', '100.00', '0.00', '0.00');
        $mixed = $this->sale('mixed', '40.00', '60.00', '50.00', '10.00');
        $legacy = $this->sale(null, null, null, null, null);

        $detail = app(SaleController::class)->show(
            $mixed,
            app(SaleFinancialSnapshotService::class)
        )->render();
        $history = app(SaleController::class)->history()->render();

        $this->assertStringContainsString('วิธีชำระเงิน', $detail);
        $this->assertStringContainsString('เงินสด + พร้อมเพย์', $detail);
        $this->assertStringContainsString('ยอดชำระเงินสด', $detail);
        $this->assertStringContainsString('ยอดชำระพร้อมเพย์', $detail);
        $this->assertStringContainsString('รับเงินสด', $detail);
        $this->assertStringContainsString('เงินทอน', $detail);
        $this->assertStringNotContainsString('>mixed<', $detail);
        $this->assertStringContainsString('เงินสด', $history);
        $this->assertStringContainsString('พร้อมเพย์', $history);
        $this->assertStringContainsString('ไม่ระบุ', $history);
    }

    public function test_tax_invoice_in_v2_uses_the_delivery_note_payment_region(): void
    {
        $cashSale = $this->sale('cash', '100.00', '0.00', '150.00', '50.00');
        $promptpaySale = $this->sale('promptpay', '0.00', '100.00', '0.00', '0.00');
        $mixedSale = $this->sale('mixed', '40.00', '60.00', '50.00', '10.00');
        $legacySale = $this->sale(null, null, null, null, null);
        $service = app(CommercialDocumentService::class);

        $taxInvoice = view('sales.invoice_v2', [
            'sale' => $cashSale,
            'setting' => null,
            'document' => $service->buildSaleDocument($cashSale, 'tax-invoice'),
        ])->render();
        $promptpayTaxInvoice = view('sales.invoice_v2', [
            'sale' => $promptpaySale,
            'setting' => null,
            'document' => $service->buildSaleDocument($promptpaySale, 'tax-invoice'),
        ])->render();
        $mixedTaxInvoice = view('sales.invoice_v2', [
            'sale' => $mixedSale,
            'setting' => null,
            'document' => $service->buildSaleDocument($mixedSale, 'tax-invoice'),
        ])->render();
        $deliveryNote = view('sales.invoice_v2', [
            'sale' => $cashSale,
            'setting' => null,
            'document' => $service->buildSaleDocument($cashSale, 'delivery-note'),
        ])->render();
        $quotation = view('sales.invoice_v2', [
            'sale' => $cashSale,
            'setting' => null,
            'document' => $service->buildSaleDocument($cashSale, 'quotation'),
        ])->render();
        $legacyTaxInvoice = view('sales.invoice_v2', [
            'sale' => $legacySale,
            'setting' => null,
            'document' => $service->buildSaleDocument($legacySale, 'tax-invoice'),
        ])->render();

        foreach ([$taxInvoice, $promptpayTaxInvoice, $mixedTaxInvoice] as $invoice) {
            $this->assertStringContainsString('class="delivery-note"', $invoice);
            $this->assertStringContainsString('class="payment-summary-section"', $invoice);
            $this->assertStringContainsString('class="qr-payment"', $invoice);
        }
        $this->assertStringNotContainsString('class="delivery-note"', $quotation);
        $this->assertStringContainsString('class="delivery-note"', $legacyTaxInvoice);
    }

    public function test_delivery_note_uses_the_redesigned_footer_and_preserves_sale_values(): void
    {
        $sale = $this->sale('cash', '100.00', '0.00', '100.00', '0.00');
        $html = view('sales.invoice_v2', [
            'sale' => $sale,
            'setting' => null,
            'document' => app(CommercialDocumentService::class)
                ->buildSaleDocument($sale, 'delivery-note'),
        ])->render();

        $this->assertStringContainsString('class="delivery-note"', $html);
        $this->assertStringContainsString('class="payment-summary-section"', $html);
        $this->assertStringContainsString('class="qr-payment"', $html);
        $this->assertStringContainsString('class="notes-block"', $html);
        $this->assertStringContainsString('class="summary"', $html);
        $this->assertStringContainsString('class="delivery-footer"', $html);
        $this->assertStringNotContainsString('class="receiver-section"', $html);
        $this->assertStringNotContainsString('class="signature-line"', $html);
        $this->assertStringContainsString('class="items-table delivery-note-items"', $html);
        $this->assertSame(5, preg_match_all('/<th\b/', $html));
        $this->assertStringContainsString($sale->sale_no, $html);
        $this->assertStringContainsString('100', $html);
    }

    public function test_delivery_note_uses_a4_by_default_and_accepts_a5_paper_query(): void
    {
        $sale = $this->sale('cash', '100.00', '0.00', '100.00', '0.00');
        $document = app(CommercialDocumentService::class)
            ->buildSaleDocument($sale, 'delivery-note');

        $a4 = view('sales.invoice_v2', [
            'sale' => $sale,
            'setting' => null,
            'document' => $document,
        ])->render();

        $this->assertStringContainsString('class="invoice paper-a4"', $a4);

        $this->app->instance('request', Request::create('/sales/1/invoice-v2?paper=a5'));
        $a5 = view('sales.invoice_v2', [
            'sale' => $sale,
            'setting' => null,
            'document' => $document,
        ])->render();

        $this->assertStringContainsString('class="invoice paper-a5"', $a5);
    }

    public function test_combined_delivery_receipt_renderers_show_payment_and_legacy_sales_omit_it(): void
    {
        $cashSale = $this->sale('cash', '100.00', '0.00', '150.00', '50.00');
        $legacySale = $this->sale(null, null, null, null, null);

        $invoice = view('sales.invoice', ['sale' => $cashSale, 'setting' => null])->render();
        $print = view('sales.print', ['sale' => $cashSale, 'setting' => null])->render();
        $legacyInvoice = view('sales.invoice', ['sale' => $legacySale, 'setting' => null])->render();

        $this->assertStringContainsString('วิธีชำระเงิน', $invoice);
        $this->assertStringContainsString('รับเงินสด', $invoice);
        $this->assertStringContainsString('วิธีชำระเงิน', $print);
        $this->assertStringNotContainsString('วิธีชำระเงิน', $legacyInvoice);
    }

    public function test_legacy_invoice_renders_the_fulfillment_label_from_delivery_type(): void
    {
        $pickup = Sale::query()->create([
            'sale_no' => 'PICKUP-'.str()->uuid(),
            'sale_date' => '2026-07-23',
            'total_amount' => '15.00',
            'delivery_fee' => '0.00',
            'discount' => '0.00',
            'delivery_type' => 'pickup',
            'customer_delivery_address_id' => null,
        ]);
        $delivery = Sale::query()->create([
            'sale_no' => 'DELIVERY-'.str()->uuid(),
            'sale_date' => '2026-07-23',
            'total_amount' => '15.00',
            'delivery_fee' => '0.00',
            'discount' => '0.00',
            'delivery_type' => 'delivery',
            'customer_delivery_address_id' => null,
        ]);

        $pickupInvoice = view('sales.invoice', ['sale' => $pickup, 'setting' => null])->render();
        $deliveryInvoice = view('sales.invoice', ['sale' => $delivery, 'setting' => null])->render();

        $this->assertStringContainsString('ลูกค้ารับเอง', $pickupInvoice);
        $this->assertStringNotContainsString('🚚 จัดส่ง', $pickupInvoice);
        $this->assertStringContainsString('🚚 จัดส่ง', $deliveryInvoice);
        $this->assertStringNotContainsString('ลูกค้ารับเอง', $deliveryInvoice);
    }

    private function sale(
        ?string $method,
        ?string $cash,
        ?string $promptpay,
        ?string $received,
        ?string $change
    ): Sale {
        return Sale::query()->create([
            'sale_no' => 'PAY-'.str()->uuid(),
            'sale_date' => '2026-07-18',
            'total_amount' => '100.00',
            'delivery_fee' => '0.00',
            'discount' => '0.00',
            'delivery_type' => 'pickup',
            'payment_method' => $method,
            'cash_amount' => $cash,
            'promptpay_amount' => $promptpay,
            'received_amount' => $received,
            'change_amount' => $change,
        ]);
    }
}
