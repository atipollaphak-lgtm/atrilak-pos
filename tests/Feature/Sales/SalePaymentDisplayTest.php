<?php

namespace Tests\Feature\Sales;

use App\Http\Controllers\SaleController;
use App\Models\Sale;
use App\Services\CommercialDocumentService;
use App\Services\Sales\SaleFinancialSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_only_tax_invoice_in_v2_renders_stored_payment_details(): void
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

        $this->assertStringContainsString('วิธีชำระเงิน', $taxInvoice);
        $this->assertStringContainsString('รับเงินสด', $taxInvoice);
        $this->assertStringContainsString('เงินทอน', $taxInvoice);
        $this->assertStringNotContainsString('ยอดชำระพร้อมเพย์', $taxInvoice);
        $this->assertStringContainsString('พร้อมเพย์', $promptpayTaxInvoice);
        $this->assertStringContainsString('ยอดชำระพร้อมเพย์', $promptpayTaxInvoice);
        $this->assertStringNotContainsString('รับเงินสด', $promptpayTaxInvoice);
        $this->assertStringContainsString('เงินสด + พร้อมเพย์', $mixedTaxInvoice);
        $this->assertStringContainsString('ยอดชำระเงินสด', $mixedTaxInvoice);
        $this->assertStringContainsString('ยอดชำระพร้อมเพย์', $mixedTaxInvoice);
        $this->assertStringNotContainsString('วิธีชำระเงิน', $deliveryNote);
        $this->assertStringNotContainsString('วิธีชำระเงิน', $quotation);
        $this->assertStringNotContainsString('วิธีชำระเงิน', $legacyTaxInvoice);
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
