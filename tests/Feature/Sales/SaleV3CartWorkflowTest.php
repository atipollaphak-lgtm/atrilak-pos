<?php

namespace Tests\Feature\Sales;

use Tests\TestCase;

class SaleV3CartWorkflowTest extends TestCase
{
    public function test_v3_frontend_contains_merge_edit_and_keyboard_contracts(): void
    {
        $script = file_get_contents(base_path('public/js/modules/sale-v3.js'));

        $this->assertStringContainsString('productUnitId', $script);
        $this->assertStringContainsString('F9', $script);
        $this->assertStringContainsString('v3-quantity-input', $script);
        $this->assertStringContainsString('createSubmissionGuard', $script);
        $this->assertStringContainsString('sales.v3.store', file_get_contents(base_path('routes/web.php')));
    }
}
