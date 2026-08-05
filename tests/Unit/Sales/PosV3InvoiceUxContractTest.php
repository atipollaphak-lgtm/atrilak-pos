<?php

namespace Tests\Unit\Sales;

use PHPUnit\Framework\TestCase;

class PosV3InvoiceUxContractTest extends TestCase
{
    public function test_pos_v3_separates_sale_date_from_delivery_date_and_keeps_server_sale_date_authoritative(): void
    {
        $sale = $this->source('public/js/modules/sale-v3.js');
        $final = $this->source('public/js/modules/final-pos.js');
        $controller = $this->source('app/Http/Controllers/SaleV3Controller.php');

        $this->assertStringContainsString('delivery_date:', $sale);
        $this->assertStringNotContainsString('sale_date: $("#v3-sale-date").value', $sale);
        $this->assertStringContainsString('delivery_date', $final);
        $this->assertStringContainsString("'sale_date' => now()->toDateString()", $controller);
        $this->assertStringContainsString("'delivery_date' => \$validated['delivery_date'] ?? null", $controller);
    }

    public function test_cart_rows_have_delete_only_and_keep_inline_price_editing(): void
    {
        $sale = $this->source('public/js/modules/sale-v3.js');
        $css = $this->source('public/css/sale-v3.css');

        $this->assertStringContainsString('v3-cart-remove', $sale);
        $this->assertStringContainsString('data-action="remove"', $sale);
        $this->assertStringContainsString('event.target.dataset.action !== "remove"', $sale);
        $this->assertStringContainsString('state.cart.splice(Number(row.dataset.index), 1)', $sale);
        $this->assertStringContainsString('function add(', $sale);
        $this->assertStringContainsString('class="v3-cart-quantity"', $sale);
        $this->assertStringNotContainsString('restore.dataset.action = "restore"', $sale);
        $this->assertStringNotContainsString('action !== "restore"', $sale);
        $this->assertStringNotContainsString('v3-edit-', $sale);
        $this->assertStringContainsString('input.className = "v3-cart-unit-price"', $sale);
        $this->assertStringNotContainsString('.v3-cart-actions', $css);
    }

    public function test_product_card_refreshes_from_the_same_pricing_context_before_first_render(): void
    {
        $sale = $this->source('public/js/modules/sale-v3.js');

        $this->assertMatchesRegularExpression(
            '/function init\(\) \{\s*refreshPricingContext\(\);\s*render\(\);/s',
            $sale
        );
        $this->assertStringContainsString('label.textContent = `${money(unitPrice(unitFor(product), 1, product))} บาท`', $sale);
        $this->assertStringContainsString('selling_price: Number(item.price).toFixed(2)', $sale);
    }

    public function test_pos_v3_exposes_notes_and_truthful_customer_and_tax_copy(): void
    {
        $cart = $this->source('resources/views/sales-v3/partials/cart.blade.php');
        $customer = $this->source('resources/views/sales-v3/partials/customer-bar.blade.php');
        $modal = $this->source('resources/views/sales-v3/partials/final-payment-modal.blade.php');
        $final = $this->source('public/js/modules/final-pos.js');

        $this->assertStringNotContainsString('id="v3-note-button" type="button" class="d-none"', $cart);
        $this->assertStringContainsString('id="v3-note-button"', $cart);
        $this->assertStringContainsString('ลูกค้าทั่วไป', $customer);
        $this->assertStringContainsString('final-tax-help', $modal);
        $this->assertStringContainsString('taxNumber', $final);
        $this->assertStringContainsString('showFeedback', $final);
    }

    public function test_invoice_documents_use_the_shared_clean_table_and_three_column_footer(): void
    {
        $view = $this->source('resources/views/sales/invoice_v2/delivery-note.blade.php');
        $css = $this->source('public/css/sales-invoice-v2.css');
        $settings = $this->source('resources/views/settings/index.blade.php');

        $this->assertStringContainsString('receipt_footer', $view);
        $this->assertStringContainsString('qr-payment', $view);
        $this->assertStringContainsString('notes-block', $view);
        $this->assertStringContainsString('summary', $view);
        $this->assertStringNotContainsString('receiver-section', $view);
        $this->assertStringNotContainsString('ผู้รับสินค้า', $view);
        $this->assertStringContainsString('border-right', $css);
        $this->assertStringContainsString('border-bottom: 1px solid', $css);
        $this->assertStringContainsString('overflow-wrap: anywhere', $css);
        $this->assertStringNotContainsString('.items-table td { height: 6mm; padding: 1mm 2mm; border-top:', $css);
        $this->assertStringContainsString('name="receipt_footer"', $settings);
    }

    public function test_invoice_design_highlights_table_header_and_grand_total(): void
    {
        $css = $this->source('public/css/sales-invoice-v2.css');

        $this->assertStringContainsString('--delivery-table-header: #f3f4f6;', $css);
        $this->assertStringContainsString('background: var(--delivery-table-header);', $css);
        $this->assertStringContainsString('color: var(--delivery-primary);', $css);
        $this->assertStringContainsString('border-bottom: 1px solid var(--delivery-primary);', $css);
        $this->assertStringContainsString('--delivery-column-divider: 1px solid var(--delivery-border);', $css);
        $this->assertSame(2, substr_count($css, 'border-right: var(--delivery-column-divider);'));
        $this->assertStringContainsString(
            '--delivery-row-divider: 1px solid var(--delivery-light-border);',
            $css
        );
        $this->assertStringContainsString('border-bottom: var(--delivery-row-divider);', $css);
        $this->assertStringContainsString(
            '.delivery-note[data-document-type="tax-invoice"] .items-table td',
            $css
        );
        $this->assertStringContainsString('padding-top: .75mm;', $css);
        $this->assertStringContainsString('padding-bottom: .75mm;', $css);
        $this->assertStringContainsString('box-shadow: inset 0 0 0 1px var(--delivery-border);', $css);
        $this->assertStringContainsString('border-radius: 2mm;', $css);
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 3).'/'.$path);

        $this->assertNotFalse($source);

        return $source;
    }
}
