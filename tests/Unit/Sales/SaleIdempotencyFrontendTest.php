<?php

namespace Tests\Unit\Sales;

use PHPUnit\Framework\TestCase;

class SaleIdempotencyFrontendTest extends TestCase
{
    public function test_pos_v1_uses_uuid_guard_and_waits_for_confirmed_success(): void
    {
        $blade = file_get_contents(dirname(__DIR__, 3).'/resources/views/sales/index.blade.php');
        $script = file_get_contents(dirname(__DIR__, 3).'/public/js/modules/pos-v1-submit.js');

        $this->assertStringContainsString('name="idempotency_key"', $blade);
        $this->assertStringContainsString('sale-intent-storage.js', $blade);
        $this->assertStringContainsString('pos-v1-submit.js', $blade);
        $this->assertStringNotContainsString('}, 800);', $blade);
        $this->assertStringContainsString('atrilak.pos.v1.pending-sale.v1', $script);
        $this->assertStringContainsString('if (!submissionGuard.start())', $script);
        $this->assertStringContainsString('button.disabled = true', $script);
        $this->assertStringContainsString('await pendingIntent.keyFor(', $script);
        $this->assertStringContainsString('new FormData(form)', $script);
        $this->assertStringContainsString('window.open("", "_blank")', $script);
        $this->assertLessThan(
            strpos($script, 'await pendingIntent.keyFor('),
            strpos($script, 'window.open("", "_blank")')
        );
        $this->assertStringContainsString('pendingIntent.clear(intent.key)', $script);
        $this->assertStringContainsString('isDefinitiveClientError(error.status)', $script);
        $this->assertStringContainsString('submissionGuard.release()', $script);
        $this->assertStringContainsString('window.location.assign(form.dataset.successUrl)', $script);
    }

    public function test_pos_v2_retains_pending_key_until_success_and_guards_duplicate_submit(): void
    {
        $blade = file_get_contents(dirname(__DIR__, 3).'/resources/views/sales/index_v2.blade.php');
        $script = file_get_contents(dirname(__DIR__, 3).'/public/js/modules/pos-submit.js');

        $this->assertStringContainsString('sale-intent-storage.js', $blade);
        $this->assertStringContainsString('atrilak.pos.v2.pending-sale.v1', $script);
        $this->assertStringContainsString('if (!submissionGuard.start())', $script);
        $this->assertStringContainsString('button.disabled = true', $script);
        $this->assertStringContainsString('intent = await pendingIntent.keyFor(payload)', $script);
        $this->assertStringContainsString('payload.idempotency_key = intent.key', $script);
        $this->assertStringContainsString('pendingIntent.clear(intent.key)', $script);
        $this->assertStringContainsString('isDefinitiveClientError(error.status)', $script);
        $this->assertStringContainsString('submissionGuard.release()', $script);
        $this->assertStringNotContainsString('window.open(', $script);
        $this->assertStringContainsString('if (!data.success || !data.invoice_url)', $script);
        $this->assertStringContainsString('resetPOS()', $script);
        $this->assertStringContainsString('window.location.assign(invoiceUrl)', $script);
        $successReset = "            resetPOS();";
        $this->assertLessThan(
            strpos($script, $successReset),
            strpos($script, 'pendingIntent.clear(intent.key)')
        );
        $this->assertLessThan(
            strpos($script, 'window.location.assign(invoiceUrl)'),
            strpos($script, $successReset)
        );
    }

    public function test_pending_intent_helper_uses_session_storage_without_storing_payload(): void
    {
        $script = file_get_contents(dirname(__DIR__, 3).'/public/js/modules/sale-intent-storage.js');

        $this->assertStringContainsString('root.sessionStorage', $script);
        $this->assertStringContainsString('DEFAULT_TTL_MS = 2 * 60 * 60 * 1000', $script);
        $this->assertStringContainsString('fingerprint: payloadFingerprint', $script);
        $this->assertStringNotContainsString('payload:', $script);
        $this->assertStringContainsString('cryptoApi.subtle.digest("SHA-256"', $script);
    }
}
