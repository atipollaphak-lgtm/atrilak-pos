<?php

namespace Tests\Unit\Services\Pricing;

use App\Services\Pricing\AverageCostService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AverageCostServiceTest extends TestCase
{
    #[DataProvider('averageCostCases')]
    public function test_it_preserves_the_current_weighted_average_cost_rule(
        float $oldQty,
        float $oldCost,
        float $receiveQty,
        float $receiveCost,
        float $expected
    ): void {
        $service = new AverageCostService;

        $this->assertSame(
            $expected,
            $service->calculate($oldQty, $oldCost, $receiveQty, $receiveCost)
        );
    }

    public static function averageCostCases(): array
    {
        return [
            'weighted stock and receipt' => [10, 100, 5, 130, 110.0],
            'first receipt uses receipt cost' => [0, 0, 8, 75.25, 75.25],
            'result rounds to two decimals' => [2, 10, 1, 11, 10.33],
            'non-positive total quantity falls back to receipt cost' => [-5, 100, 5, 80.126, 80.13],
        ];
    }
}
