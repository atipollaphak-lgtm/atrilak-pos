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
        $this->assertStringContainsString('pos-v1-submit.js', $blade);
        $this->assertStringNotContainsString('}, 800);', $blade);
        $this->assertStringContainsString('crypto.randomUUID()', $script);
        $this->assertStringContainsString('if (isSubmitting)', $script);
        $this->assertStringContainsString('button.disabled = true', $script);
        $this->assertStringContainsString('new FormData(form)', $script);
        $this->assertStringContainsString('window.open("", "_blank")', $script);
        $this->assertStringContainsString('window.location.assign(form.dataset.successUrl)', $script);
    }

    public function test_pos_v2_retains_pending_key_until_success_and_guards_duplicate_submit(): void
    {
        $script = file_get_contents(dirname(__DIR__, 3).'/public/js/modules/pos-submit.js');

        $this->assertStringContainsString('crypto.randomUUID()', $script);
        $this->assertStringContainsString('if (isSubmitting)', $script);
        $this->assertStringContainsString('button.disabled = true', $script);
        $this->assertStringContainsString('payload.idempotency_key = pendingSale.key', $script);
        $this->assertStringContainsString('pendingSale = null', $script);
        $this->assertStringContainsString('window.open(data.invoice_url, "_blank")', $script);
        $this->assertStringContainsString('resetPOS()', $script);
    }
}
