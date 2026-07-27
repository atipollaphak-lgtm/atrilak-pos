<?php

namespace Tests\Unit\Sales;

use PHPUnit\Framework\TestCase;

class PosV2CartIdentityTest extends TestCase
{
    public function test_cart_actions_identify_rows_by_product_and_product_unit(): void
    {
        $script = file_get_contents(dirname(__DIR__, 3).'/public/js/modules/pos-cart.js');

        $this->assertIsString($script);
        $this->assertStringContainsString('function findCartIndex(productId, productUnitId)', $script);
        $this->assertStringContainsString('data-product-id="${item.productId}"', $script);
        $this->assertStringContainsString('data-product-unit-id="${item.productUnitId ?? ""}"', $script);
        $this->assertGreaterThanOrEqual(3, substr_count($script, 'findCartIndex('));
        $this->assertStringContainsString('forceProductUnitId: item.productUnitId', $script);
        $this->assertStringContainsString('getUnitPrice(', $script);
        $this->assertStringContainsString('if (qty >= Number(tier.min_qty))', $script);
        $this->assertStringNotContainsString('getUnitPrice(unit, baseQty)', $script);
    }
}
