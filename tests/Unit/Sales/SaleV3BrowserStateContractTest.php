<?php

namespace Tests\Unit\Sales;

use PHPUnit\Framework\TestCase;

class SaleV3BrowserStateContractTest extends TestCase
{
    public function test_sale_v3_keeps_customer_address_and_fulfillment_in_one_draft_state(): void
    {
        $sale = $this->source('public/js/modules/sale-v3.js');
        $final = $this->source('public/js/modules/final-pos.js');

        foreach (['customerId', 'addressId', 'deliveryType', 'buildPayload(payment)', 'state.customerId', 'state.addressId', 'state.deliveryType'] as $marker) {
            $this->assertStringContainsString($marker, $sale);
        }

        $this->assertStringContainsString('setCustomer', $final);
        $this->assertStringContainsString('setDeliveryType', $final);
        $this->assertStringContainsString('await context.setCustomer', $final);
        $this->assertStringContainsString("context.state.draftZone = null;\n        context.state.zone = null;\n        context.customerSelect.dispatchEvent", $final);
        $this->assertStringContainsString('context.setDeliveryType(hold.delivery_type)', $final);
        $this->assertStringContainsString("$('#v3-customer-summary')", $final);
        $this->assertStringNotContainsString('pickupSuffix', $final);
        $this->assertStringContainsString('filterCustomers();', $final);
    }

    public function test_payment_payload_is_built_from_the_canonical_draft_state(): void
    {
        $sale = $this->source('public/js/modules/sale-v3.js');
        $payloadStart = strpos($sale, 'function buildPayload(payment)');

        $this->assertNotFalse($payloadStart);
        $payload = substr($sale, $payloadStart, strpos($sale, 'function init()', $payloadStart) - $payloadStart);

        $this->assertStringContainsString('customer_id: state.customerId || null', $payload);
        $this->assertStringContainsString('customer_delivery_address_id: state.addressId || null', $payload);
        $this->assertStringContainsString('delivery_type: state.deliveryType', $payload);
        $this->assertStringNotContainsString('customer_id: $("#v3-customer-id").value', $payload);
        $this->assertStringNotContainsString('delivery_type: $("#v3-pickup").checked', $payload);
    }

    public function test_sale_v3_defaults_to_pickup_and_sends_price_edit_intent(): void
    {
        $sale = $this->source('public/js/modules/sale-v3.js');

        $this->assertStringContainsString('deliveryType: "pickup"', $sale);
        $this->assertStringContainsString('price_was_edited: Boolean(item.priceWasEdited)', $sale);
        $this->assertStringContainsString(
            'price_changed_since_hold: Boolean(item.priceChangedSinceHold)',
            $sale
        );
        $this->assertStringContainsString('if (item.priceWasEdited) item.originalPrice', $sale);
        $this->assertStringNotContainsString('restore.dataset.action = "restore"', $sale);
    }

    public function test_delivery_draft_derives_zone_from_address_and_requires_explicit_multiple_address_selection(): void
    {
        $sale = $this->source('public/js/modules/sale-v3.js');
        $customer = $this->source('resources/views/sales-v3/partials/customer-bar.blade.php');
        $navigation = $this->source('resources/views/sales-v3/partials/product-navigation.blade.php');

        $this->assertStringContainsString('state.draftZone', $sale);
        $this->assertStringContainsString('state.addresses.length === 1', $sale);
        $this->assertStringContainsString('state.addresses.length > 1', $sale);
        $this->assertStringContainsString('const defaultAddress = state.addresses.find', $sale);
        $this->assertStringContainsString('const nextZone = nextAddress?.delivery_zone || null', $sale);
        $this->assertStringContainsString('state.zone = state.deliveryType === "delivery" ? nextZone : null', $sale);
        $this->assertStringContainsString('state.deliveryFeeEdited = false', $sale);
        $this->assertStringContainsString('id="v3-price-zone-select"', $customer);
        $this->assertStringContainsString('aria-label="โซนราคาตามที่อยู่ลูกค้า" disabled', $customer);
        $this->assertStringNotContainsString('v3-price-zone-select', $navigation);
    }

    public function test_quantity_modal_exposes_touch_controls_and_price_context(): void
    {
        $modal = $this->source('resources/views/sales-v3/partials/quantity-modal.blade.php');
        $sale = $this->source('public/js/modules/sale-v3.js');

        foreach (['v3-quantity-decrease', 'v3-quantity-increase', 'v3-quantity-unit', 'v3-quantity-price', 'v3-quantity-total'] as $marker) {
            $this->assertStringContainsString($marker, $modal);
        }

        $this->assertStringContainsString('syncQuantityPreview', $sale);
    }

    public function test_mobile_customer_identity_and_address_remain_visible_without_ellipsis(): void
    {
        $css = $this->source('public/css/sale-v3.css');
        $mobileStart = strpos($css, '@media (max-width:575px)');

        $this->assertNotFalse($mobileStart);

        $mobileCss = substr($css, $mobileStart);
        $expectedMobileRule = implode(chr(10), [
            '.pos-v3-customer-summary,',
            '    .pos-v3-customer-line {',
            '        overflow:visible;',
            '        text-overflow:clip;',
            '        white-space:normal;',
            '        overflow-wrap:anywhere;',
            '    }',
        ]);
        $this->assertStringContainsString(
            $expectedMobileRule,
            $mobileCss
        );
    }

    public function test_customer_identity_and_address_do_not_hide_phone_or_address_on_desktop(): void
    {
        $css = $this->source('public/css/sale-v3.css');
        $mobileStart = strpos($css, '@media (max-width:575px)');

        $this->assertNotFalse($mobileStart);

        $baseCss = substr($css, 0, $mobileStart);
        $this->assertStringContainsString(
            '.pos-v3-customer-summary { display:block; overflow:visible; font-size:17px; text-overflow:clip; white-space:normal; overflow-wrap:anywhere; }',
            $baseCss
        );
        $this->assertStringContainsString(
            '.pos-v3-customer-line { display:block; margin-top:3px; overflow:visible; color:var(--pos-muted); font-size:12px; text-overflow:clip; white-space:normal; overflow-wrap:anywhere; }',
            $baseCss
        );
    }

    public function test_unit_price_is_inline_editable_without_opening_a_price_popup(): void
    {
        $sale = $this->source('public/js/modules/sale-v3.js');

        $this->assertStringContainsString('input.className = "v3-cart-unit-price"', $sale);
        $this->assertStringContainsString('input.inputMode = "decimal"', $sale);
        $this->assertStringContainsString('function commitUnitPrice', $sale);
        $this->assertStringContainsString('event.target.matches(".v3-cart-unit-price")', $sale);
        $this->assertStringContainsString('priceCell.value = Number(item.price).toFixed(2)', $sale);
        $this->assertStringNotContainsString('restore.dataset.action = "restore"', $sale);
        $this->assertStringNotContainsString('action === "edit"', $sale);
    }

    public function test_final_payment_uses_direct_cash_confirmation_and_shows_documents_after_success(): void
    {
        $final = $this->source('public/js/modules/final-pos.js');

        $this->assertStringContainsString('confirmDefaultCash()', $final);
        $this->assertStringContainsString("$('#final-document-panel')?.classList.remove('d-none')", $final);
        $this->assertStringContainsString("$('#final-print-delivery')", $final);
        $this->assertStringContainsString("$('#final-print-tax')", $final);
        $this->assertStringNotContainsString('final-print-documents', $final);
        $this->assertStringContainsString('ensurePaymentMethodSummary', $final);
    }

    public function test_payment_summary_is_compact_and_includes_quantity_unit(): void
    {
        $final = $this->source('public/js/modules/final-pos.js');
        $modal = $this->source('resources/views/sales-v3/partials/final-payment-modal.blade.php');

        $this->assertStringContainsString('<td>${item.qty} ${escapeHtml(item.unitName)}</td><td>${money(item.qty * item.price)}</td>', $final);
        $this->assertStringContainsString('<th>จำนวน</th>', $modal);
        $this->assertStringContainsString('<th>รวม</th>', $modal);
        $this->assertStringNotContainsString('<th>ราคา</th>', $modal);
    }

    public function test_fulfillment_buttons_expose_one_truthful_selected_state(): void
    {
        $cart = $this->source('resources/views/sales-v3/partials/cart.blade.php');
        $sale = $this->source('public/js/modules/sale-v3.js');

        $this->assertStringContainsString('class="fulfillment-check"', $cart);
        $this->assertStringContainsString('aria-pressed="false"', $cart);
        $this->assertStringContainsString('setAttribute("aria-pressed"', $sale);
        $this->assertStringContainsString('is-selected', $sale);
        $this->assertStringContainsString('state.deliveryType === "pickup"', $sale);
        $this->assertStringContainsString('pos-v3-fulfillment-status', $sale);
        $this->assertStringContainsString('noteStatus?.addEventListener("click"', $sale);
        $this->assertStringNotContainsString(
            'const fulfillmentText = $("#v3-pickup").checked',
            $sale
        );
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 3).'/'.$path);

        $this->assertNotFalse($source);

        return $source;
    }
}
