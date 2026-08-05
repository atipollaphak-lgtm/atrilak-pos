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
        $invoice = $this->source('resources/views/sales/invoice_v2.blade.php');
        $css = $this->source('public/css/sales-invoice-v2.css');
        $settings = $this->source('resources/views/settings/index.blade.php');

        $this->assertStringContainsString('receipt_footer', $view);
        $this->assertStringContainsString('qr-payment', $view);
        $this->assertStringContainsString('notes-block', $view);
        $this->assertStringContainsString('summary', $view);
        $this->assertStringContainsString('delivery-footer-message', $view);
        $this->assertStringContainsString('qr-empty', $view);
        $this->assertStringContainsString('notes-empty', $view);
        $this->assertStringNotContainsString('ATRILAK BUILDING SOLUTIONS', $view);
        $this->assertStringNotContainsString('ATRILAK BUILDING SOLUTIONS', $invoice);
        $this->assertStringContainsString('$minimumRows', $view);
        $this->assertStringNotContainsString('receiver-section', $view);
        $this->assertStringNotContainsString('ผู้รับสินค้า', $view);
        $this->assertStringContainsString('border-right', $css);
        $this->assertStringContainsString('border-top: 1px solid var(--delivery-light-border);', $css);
        $this->assertStringContainsString('overflow-wrap: anywhere', $css);
        $this->assertStringContainsString('.items-table td { height: 6mm; padding: 1mm 2mm;', $css);
        $this->assertStringContainsString('name="receipt_footer"', $settings);
    }

    public function test_invoice_preserves_production_proportions_while_adding_approved_features(): void
    {
        $css = $this->source('public/css/sales-invoice-v2.css');

        $this->assertStringContainsString('padding: 7mm 8mm 4mm;', $css);
        $this->assertStringContainsString('gap: 8mm;', $css);
        $this->assertStringContainsString('height: 6mm;', $css);
        $this->assertStringContainsString('padding: 1mm 2mm;', $css);
        $this->assertStringContainsString('margin-top: 2mm;', $css);
        $this->assertStringContainsString('width: 32mm;', $css);
        $this->assertStringContainsString('min-height: 20mm;', $css);
        $this->assertStringContainsString('display: table-cell;', $css);
        $this->assertStringContainsString(
            '.qr-payment { display: table-cell; box-sizing: border-box; width: 20%;',
            $css
        );
        $this->assertStringContainsString(
            '.notes-block { display: table-cell; width: 35%;',
            $css
        );
        $this->assertStringContainsString(
            '.summary { display: table-cell; width: 45%;',
            $css
        );
        $this->assertStringContainsString(
            '.summary div strong { text-align: right; white-space: nowrap; }',
            $css
        );
        $this->assertStringContainsString(
            '.qr-empty { display: block; color: var(--delivery-muted);',
            $css
        );
        $this->assertStringContainsString('.notes-empty { color: #9ca3af; }', $css);
        $this->assertStringContainsString(
            "font-size: 14px;\n    background: #f3f4f6;",
            $css
        );
        $this->assertStringContainsString(
            ".paper-a5 .delivery-note {\n    width: 148mm;\n    min-height: 0;\n    padding: 5mm 5mm 4mm;\n    font-size: 12px;\n}",
            $css
        );
        $this->assertStringContainsString('font-size: 15px;', $css);
        $this->assertStringContainsString('text-overflow: clip;', $css);
        $this->assertStringNotContainsString('text-overflow: ellipsis', $css);
        $this->assertStringNotContainsString('--delivery-document-font-size', $css);
        $this->assertStringNotContainsString('--delivery-a5-font-size', $css);
        $this->assertStringNotContainsString('grid-template-columns: 25% 40% 35%;', $css);
        $this->assertStringNotContainsString('min-height: 40mm;', $css);
        $this->assertStringNotContainsString('width: 36mm;', $css);
        $this->assertStringNotContainsString('.item-cell-content', $css);
    }

    public function test_invoice_keeps_production_spacing_and_footer_dimensions(): void
    {
        $css = $this->source('public/css/sales-invoice-v2.css');

        $this->assertStringContainsString('padding-bottom: 3mm;', $css);
        $this->assertStringContainsString('padding: 2.5mm 4mm;', $css);
        $this->assertStringContainsString('line-height: 1.35;', $css);
        $this->assertStringContainsString(
            '.delivery-footer { display: flex; flex-direction: column; align-items: center; gap: 1mm; margin-top: 2mm;',
            $css
        );
        $this->assertStringContainsString('.paper-a5 .delivery-qr { width: 26mm; height: auto; }', $css);
        $this->assertStringContainsString('.paper-a5 .notes-block { min-height: 16mm;', $css);
    }

    public function test_invoice_documents_keep_production_rows_and_approved_footer_features(): void
    {
        $css = $this->source('public/css/sales-invoice-v2.css');
        $view = $this->source('resources/views/sales/invoice_v2/delivery-note.blade.php');

        $this->assertStringContainsString('height: auto;', $css);
        $this->assertStringContainsString('.delivery-note[data-document-type="tax-invoice"] .items-table td', $css);
        $this->assertStringContainsString('width: 32mm;', $css);
        $this->assertStringContainsString('.paper-a5 .delivery-qr { width: 26mm; height: auto; }', $css);
        $this->assertStringContainsString('padding: 2.5mm 4mm;', $css);
        $this->assertStringContainsString('line-height: 1.35;', $css);
        $this->assertStringContainsString('$minimumRows', $view);
        $this->assertStringNotContainsString('item-cell-content', $view);
    }

    public function test_invoice_print_page_size_keeps_a4_default_and_conditional_a5_override(): void
    {
        $css = $this->source('public/css/sales-invoice-v2.css');
        $view = $this->source('resources/views/sales/invoice_v2.blade.php');

        $this->assertGreaterThanOrEqual(2, substr_count($css, '@page'));
        $this->assertStringContainsString('@page { size: A4 portrait; margin: 0; }', $css);
        $this->assertStringContainsString('@page { size: A5 portrait; }', $css);
        $this->assertStringContainsString("@if ((\$paper ?? 'a4') === 'a5')", $view);
        $this->assertStringContainsString('@page { size: A5 portrait; margin: 0; }', $view);
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 3).'/'.$path);

        $this->assertNotFalse($source);

        return $source;
    }
}
