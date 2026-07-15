<?php

namespace Tests\Unit\Services\Sales;

use App\Services\Sales\ProfitGuardService;
use PHPUnit\Framework\TestCase;

class ProfitGuardServiceTest extends TestCase
{
    public function test_delivery_fee_is_part_of_the_current_minimum_profit_check(): void
    {
        $result = (new ProfitGuardService)->check([
            'delivery_type' => 'delivery',
            'delivery_fee' => 50,
            'minimum_profit' => 120,
            'delivery_zone_id' => 7,
        ], 80);

        $this->assertTrue($result['passed']);
        $this->assertEquals(130.0, $result['total_profit']);
        $this->assertEquals(0.0, $result['short_amount']);
        $this->assertSame(7, $result['delivery_zone_id']);
    }

    public function test_delivery_is_rejected_by_the_current_rule_when_profit_is_short(): void
    {
        $result = (new ProfitGuardService)->check([
            'delivery_type' => 'delivery',
            'delivery_fee' => 20,
            'minimum_profit' => 150,
        ], 100);

        $this->assertFalse($result['passed']);
        $this->assertEquals(120.0, $result['total_profit']);
        $this->assertEquals(30.0, $result['short_amount']);
        $this->assertNotNull($result['message']);
    }

    public function test_pickup_zeroes_delivery_values_but_still_rejects_negative_product_profit(): void
    {
        $result = (new ProfitGuardService)->check([
            'delivery_type' => 'pickup',
            'delivery_fee' => 100,
            'minimum_profit' => 500,
        ], -25);

        $this->assertFalse($result['passed']);
        $this->assertEquals(0.0, $result['delivery_fee']);
        $this->assertEquals(0.0, $result['minimum_profit']);
        $this->assertEquals(-25.0, $result['total_profit']);
        $this->assertEquals(25.0, $result['short_amount']);
    }

    public function test_decimal_profit_comparison_is_deterministic(): void
    {
        $result = (new ProfitGuardService)->check([
            'delivery_type' => 'delivery',
            'delivery_fee' => '0.02',
            'minimum_profit' => '0.03',
        ], '0.01');

        $this->assertTrue($result['passed']);
        $this->assertSame('0.03', (string) $result['total_profit']);
        $this->assertSame('0.00', (string) $result['short_amount']);
    }
}
