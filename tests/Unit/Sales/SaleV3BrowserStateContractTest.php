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
        $this->assertStringContainsString('context.setDeliveryType(hold.delivery_type)', $final);
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
        $this->assertStringContainsString('restore.dataset.action = "restore"', $sale);
    }

    public function test_final_payment_uses_direct_cash_confirmation_and_shows_documents_after_success(): void
    {
        $final = $this->source('public/js/modules/final-pos.js');

        $this->assertStringContainsString('confirmDefaultCash()', $final);
        $this->assertStringContainsString("$('#final-document-panel')?.classList.remove('d-none')", $final);
        $this->assertStringContainsString("$('#final-print-documents').disabled = false", $final);
        $this->assertStringContainsString('ensurePaymentMethodSummary', $final);
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 3).'/'.$path);

        $this->assertNotFalse($source);

        return $source;
    }
}
