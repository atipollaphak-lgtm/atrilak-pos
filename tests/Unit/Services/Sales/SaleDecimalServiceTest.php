<?php

namespace Tests\Unit\Services\Sales;

use App\Services\Sales\SaleDecimalService;
use PHPUnit\Framework\TestCase;

class SaleDecimalServiceTest extends TestCase
{
    public function test_line_total_uses_deterministic_money_rounding(): void
    {
        $service = new SaleDecimalService;

        $this->assertSame('0.01', $service->lineTotal('0.01', '0.50'));
        $this->assertSame('0.02', $service->lineTotal('0.03', '0.50'));
    }

    public function test_header_total_is_the_sum_of_rounded_line_totals_in_original_order(): void
    {
        $service = new SaleDecimalService;

        $items = [
            ['qty' => '0.01', 'selling_price' => '0.50'],
            ['qty' => '0.03', 'selling_price' => '0.50'],
            ['qty' => '1.25', 'selling_price' => '2.40'],
        ];

        $this->assertSame('3.03', $service->itemsTotal($items));
    }

    public function test_delivery_fee_and_discount_keep_money_precision(): void
    {
        $service = new SaleDecimalService;

        $this->assertSame('10.02', $service->netTotal('10.01', '0.02', '0.01'));
    }

    public function test_stored_money_totals_are_summed_without_float_arithmetic(): void
    {
        $service = new SaleDecimalService;

        $this->assertSame('190.03', $service->sumMoney(['190.00', '0.01', '0.02']));
    }

    public function test_line_profit_uses_the_same_money_rounding(): void
    {
        $service = new SaleDecimalService;

        $this->assertSame('0.01', $service->lineProfit('0.02', '0.35', '0.10'));
    }

    public function test_base_cost_profit_rounds_revenue_and_cost_before_subtraction(): void
    {
        $service = new SaleDecimalService;

        $cost = $service->lineCost('48.0000', '5.00');
        $profit = $service->lineProfitFromBaseQuantity(
            '2.00',
            '180.00',
            '48.0000',
            '5.00'
        );

        $this->assertSame('240.00', $cost);
        $this->assertSame('120.00', $profit);
        $this->assertSame('360.00', $service->addMoney($cost, $profit));
        $this->assertSame('240.00', $service->storedLineCost('360.00', '120.00'));
    }

    public function test_commission_formulas_keep_existing_policy_with_deterministic_rounding(): void
    {
        $service = new SaleDecimalService;

        $this->assertSame('0.01', $service->percentCommission('0.05', '10.00'));
        $this->assertSame('0.01', $service->amountCommission('0.03', '0.25'));
    }
}
