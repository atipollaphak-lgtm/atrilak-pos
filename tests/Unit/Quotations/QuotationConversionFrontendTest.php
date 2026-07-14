<?php

namespace Tests\Unit\Quotations;

use PHPUnit\Framework\TestCase;

class QuotationConversionFrontendTest extends TestCase
{
    public function test_convert_form_uses_a_double_submit_guard_without_browser_idempotency(): void
    {
        $blade = file_get_contents(dirname(__DIR__, 3).'/resources/views/quotations/show.blade.php');
        $script = file_get_contents(dirname(__DIR__, 3).'/public/js/modules/quotation-convert.js');

        $this->assertStringContainsString('id="quotation-convert-form"', $blade);
        $this->assertStringContainsString('id="quotation-convert-button"', $blade);
        $this->assertStringContainsString('quotation-convert.js', $blade);
        $this->assertStringContainsString('if (isSubmitting)', $script);
        $this->assertStringContainsString('event.preventDefault()', $script);
        $this->assertStringContainsString('button.disabled = true', $script);
        $this->assertStringNotContainsString('randomUUID', $script);
        $this->assertStringNotContainsString('idempotency', $script);
    }
}
