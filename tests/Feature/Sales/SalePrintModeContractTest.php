<?php

namespace Tests\Feature\Sales;

use PHPUnit\Framework\TestCase;

class SalePrintModeContractTest extends TestCase
{
    public function test_pos_v3_print_popup_requests_auto_print_mode(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/../public/js/modules/final-pos.js');

        $this->assertNotFalse($source);
        $this->assertStringContainsString('auto_print=1', $source);
    }

    public function test_invoice_v2_has_a_conditional_afterprint_close_handler(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/../resources/views/sales/invoice_v2.blade.php');

        $this->assertNotFalse($source);
        $this->assertStringContainsString("request()->boolean('auto_print')", $source);
        $this->assertStringContainsString('afterprint', $source);
    }
}
