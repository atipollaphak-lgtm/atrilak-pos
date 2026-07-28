<?php

namespace Tests\Unit\Services\Pricing;

use App\Services\Pricing\PricingService;
use PHPUnit\Framework\TestCase;

class PricingServiceTest extends TestCase
{
    public function test_percentage_pricing_rounds_up_and_reports_markup_profit(): void
    {
        $result = (new PricingService)->calculatePrice('5.80', 'percentage', '30', 'up', '1');

        $this->assertSame('7.54', $result['price_before_round']);
        $this->assertSame('8.00', $result['final_price']);
        $this->assertSame('2.20', $result['profit_amount']);
        $this->assertSame('37.93', $result['profit_percent']);
    }

    public function test_fixed_amount_pricing_rounds_down_to_the_selected_unit(): void
    {
        $result = (new PricingService)->calculatePrice('5.80', 'fixed', '15', 'down', '0.50');

        $this->assertSame('20.80', $result['price_before_round']);
        $this->assertSame('20.50', $result['final_price']);
    }

    public function test_production_rounding_bug_fixture_has_the_same_server_result_as_the_drawer_preview(): void
    {
        $result = (new PricingService)->calculatePrice('10.33', 'percentage', '30', 'up', '1.00');

        $this->assertSame('13.43', $result['price_before_round']);
        $this->assertSame('14.00', $result['final_price']);
    }

    public function test_nearest_rounding_uses_the_selected_unit(): void
    {
        $result = (new PricingService)->calculatePrice('5.80', 'fixed', '15', 'nearest', '0.50');

        $this->assertSame('20.80', $result['price_before_round']);
        $this->assertSame('21.00', $result['final_price']);
    }

    public function test_manual_price_is_final_and_does_not_apply_rounding(): void
    {
        $result = (new PricingService)->calculatePrice('5.80', 'manual', '7.99', 'up', '1');

        $this->assertSame('7.99', $result['final_price']);
        $this->assertFalse($result['rounding_applied']);
    }

    public function test_missing_cost_does_not_calculate_a_suggested_price(): void
    {
        $result = (new PricingService)->calculatePrice(null, 'percentage', '30', 'up', '1');

        $this->assertNull($result['final_price']);
        $this->assertNull($result['profit_percent']);
    }
}
