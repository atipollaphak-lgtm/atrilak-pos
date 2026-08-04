<?php

namespace App\Services\Products;

use App\Exceptions\StaleProductCostException;
use App\Models\Product;
use App\Models\ProductPriceHistory;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;

class ProductCostAdjustmentService
{
    public function adjust(
        Product $product,
        string $newCost,
        string $expectedCost,
        string $reason,
        int $userId
    ): array {
        return DB::transaction(function () use ($product, $newCost, $expectedCost, $reason, $userId): array {
            $locked = Product::query()
                ->whereKey($product->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $oldCost = $this->money($locked->cost_price ?? '0');
            $expected = $this->money($expectedCost);
            $newCost = $this->money($newCost);

            if (BigDecimal::of($oldCost)->compareTo(BigDecimal::of($expected)) !== 0) {
                throw new StaleProductCostException;
            }

            $oldSellingPrice = $locked->selling_price === null
                ? null
                : $this->money($locked->selling_price);
            $oldAverageCost = $locked->pricing_reviewed_cost === null
                ? $oldCost
                : $this->money($locked->pricing_reviewed_cost);

            if (BigDecimal::of($oldCost)->compareTo(BigDecimal::of($newCost)) === 0) {
                return [
                    'changed' => false,
                    'old_cost' => $oldCost,
                    'new_cost' => $oldCost,
                    'delta' => '0.00',
                    'profit_before' => $this->profit($oldSellingPrice, $oldCost),
                    'profit_after' => $this->profit($oldSellingPrice, $oldCost),
                    'product' => $locked->fresh(),
                ];
            }

            $profitBefore = $this->profit($oldSellingPrice, $oldCost);
            $profitAfter = $this->profit($oldSellingPrice, $newCost);
            $profitPercentAfter = $this->profitPercent($profitAfter, $newCost);

            $locked->cost_price = $newCost;

            // Preserve an existing review marker. If none exists yet, retain the
            // old cost as the marker so Pricing Management can show pending_review.
            if ($locked->pricing_reviewed_cost === null && $locked->selling_price !== null) {
                $locked->pricing_reviewed_cost = $oldCost;
            }

            $locked->save();

            ProductPriceHistory::query()->create([
                'product_id' => $locked->id,
                'old_cost_price' => $oldCost,
                'new_cost_price' => $newCost,
                'old_selling_price' => $oldSellingPrice,
                'new_selling_price' => $oldSellingPrice,
                'old_price' => $oldSellingPrice,
                'new_price' => $oldSellingPrice,
                'old_average_cost' => $oldAverageCost,
                'pricing_method' => $locked->pricing_method,
                'pricing_source' => $locked->pricing_source,
                'pricing_value' => $locked->pricing_value,
                'rounding_direction' => $locked->rounding_direction,
                'rounding_unit' => $locked->rounding_unit,
                'average_cost' => $newCost,
                'profit_percent' => $profitPercentAfter,
                'final_price' => $oldSellingPrice,
                'profit_amount' => $profitAfter,
                'created_from' => 'manual_cost_adjustment',
                'user_id' => $userId,
                'remark' => $reason,
            ]);

            return [
                'changed' => true,
                'old_cost' => $oldCost,
                'new_cost' => $newCost,
                'delta' => BigDecimal::of($newCost)
                    ->minus($oldCost)
                    ->toScale(2, RoundingMode::HALF_UP)
                    ->__toString(),
                'profit_before' => $profitBefore,
                'profit_after' => $profitAfter,
                'product' => $locked->fresh(),
            ];
        });
    }

    private function money(mixed $value): string
    {
        return BigDecimal::of((string) $value)
            ->toScale(2, RoundingMode::HALF_UP)
            ->__toString();
    }

    private function profit(?string $sellingPrice, string $cost): ?string
    {
        if ($sellingPrice === null) {
            return null;
        }

        return BigDecimal::of($sellingPrice)
            ->minus($cost)
            ->toScale(2, RoundingMode::HALF_UP)
            ->__toString();
    }

    private function profitPercent(?string $profit, string $cost): ?string
    {
        if ($profit === null || BigDecimal::of($cost)->isZero()) {
            return null;
        }

        return BigDecimal::of($profit)
            ->dividedBy($cost, 8, RoundingMode::HALF_UP)
            ->multipliedBy('100')
            ->toScale(2, RoundingMode::HALF_UP)
            ->__toString();
    }
}
