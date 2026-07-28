<?php

namespace Tests\Unit\Services\Sales;

use App\Services\Sales\ProfitGuardService;
use PHPUnit\Framework\TestCase;

class ProfitGuardServiceTest extends TestCase
{
    public function test_delivery_fee_is_the_profit_shortfall(): void
    {
        $result = (new ProfitGuardService)->check([
            'delivery_type' => 'delivery',
            'delivery_fee' => 999,
            'minimum_profit' => 120,
            'delivery_zone_id' => 7,
        ], 80);

        $this->assertTrue($result['passed']);
        $this->assertEquals('120.00', $result['total_profit']);
        $this->assertEquals('40.00', $result['short_amount']);
        $this->assertEquals('40.00', $result['delivery_fee']);
        $this->assertSame(7, $result['delivery_zone_id']);
    }

    public function test_delivery_is_allowed_when_fee_closes_the_profit_shortfall(): void
    {
        $result = (new ProfitGuardService)->check([
            'delivery_type' => 'delivery',
            'delivery_fee' => 20,
            'minimum_profit' => 150,
        ], 100);

        $this->assertTrue($result['passed']);
        $this->assertEquals('150.00', $result['total_profit']);
        $this->assertEquals('50.00', $result['short_amount']);
        $this->assertEquals('50.00', $result['delivery_fee']);
    }

    public function test_pickup_always_has_zero_delivery_fee_and_zone_minimum(): void
    {
        $result = (new ProfitGuardService)->check([
            'delivery_type' => 'pickup',
            'delivery_fee' => 100,
            'minimum_profit' => 500,
        ], -25);

        $this->assertTrue($result['passed']);
        $this->assertEquals('0.00', $result['delivery_fee']);
        $this->assertEquals('0.00', $result['minimum_profit']);
        $this->assertEquals('-25.00', $result['total_profit']);
        $this->assertEquals('0.00', $result['short_amount']);
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
        $this->assertSame('0.02', (string) $result['short_amount']);
    }
}
