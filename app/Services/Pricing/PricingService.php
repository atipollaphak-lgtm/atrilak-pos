<?php

namespace App\Services\Pricing;

use App\Models\CategoryPricingRule;
use App\Models\Product;
use App\Models\ProductPriceHistory;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PricingService
{
    public const CATEGORY_SOURCE = 'category';

    public const METHODS = ['percentage', 'fixed', 'manual'];

    public const ROUNDING_DIRECTIONS = ['up', 'down', 'nearest'];

    public const ROUNDING_UNITS = ['0.01', '0.05', '0.10', '0.50', '1', '5', '10', '100'];

    public function calculate(Product $product, ?string $averageCost = null, ?array $overrides = null): array
    {
        $cost = $averageCost ?? ($product->cost_price !== null ? (string) $product->cost_price : null);
        $config = $this->configuration($product, $overrides);
        $calculation = $this->calculatePrice(
            $cost,
            $config['pricing_method'],
            $config['pricing_value'],
            $config['rounding_direction'],
            $config['rounding_unit']
        );

        $currentPrice = $product->selling_price !== null ? $this->money($product->selling_price) : null;
        $status = $this->status($product, $calculation['final_price'], $config);

        if (in_array($status, ['normal', 'inactive'], true) && $currentPrice !== null && $cost !== null) {
            [$calculation['profit_amount'], $calculation['profit_percent']] = $this->profitForPrice($cost, $currentPrice);
        }

        return [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'category_name' => $product->category?->name,
            'active' => (bool) $product->active,
            'status' => $status,
            'average_cost' => $cost === null ? null : $this->money($cost),
            'old_average_cost' => $product->pricing_reviewed_cost === null ? null : $this->money($product->pricing_reviewed_cost),
            'pricing_method' => $config['pricing_method'],
            'pricing_source' => $config['pricing_source'],
            'pricing_value' => $config['pricing_value'],
            'rounding_direction' => $config['rounding_direction'],
            'rounding_unit' => $config['rounding_unit'],
            'current_price' => $currentPrice,
            'old_price' => $currentPrice,
            'auto_price_enabled' => true,
            'price_lock' => false,
            'profit_percent' => $calculation['profit_percent'],
            'satang_rounding_mode' => $config['rounding_unit'],
            'baht_rounding_mode' => $config['rounding_direction'],
            'satang_rounded_price' => $calculation['final_price'],
            'suggested_price' => $calculation['final_price'],
            'final_price' => $calculation['final_price'],
            'changed' => $currentPrice !== $calculation['final_price'],
            'category_rule_available' => $config['category_rule_available'],
            'category_rule' => $config['category_rule'],
            ...$calculation,
        ];
    }

    public function calculatePrice(
        ?string $averageCost,
        string $method,
        string|float|null $value,
        ?string $roundingDirection,
        string|float|null $roundingUnit
    ): array {
        if ($value === null || $value === '') {
            return $this->emptyCalculation($method, $value, $roundingDirection, $roundingUnit);
        }

        if ($averageCost === null && $method === 'manual') {
            return [
                'profit_before_round' => null,
                'price_before_round' => $this->money($value),
                'final_price' => $this->money($value),
                'profit_amount' => null,
                'profit_percent' => null,
                'rounding_applied' => false,
            ];
        }

        if ($averageCost === null) {
            return $this->emptyCalculation($method, $value, $roundingDirection, $roundingUnit);
        }

        $cost = BigDecimal::of((string) $averageCost);
        $pricingValue = BigDecimal::of((string) $value);

        $profitBeforeRound = match ($method) {
            'percentage' => $cost->multipliedBy($pricingValue)->dividedBy('100', 8, RoundingMode::HALF_UP),
            'fixed' => $pricingValue,
            'manual' => $pricingValue->minus($cost),
            default => throw new \InvalidArgumentException('ไม่พบรูปแบบการตั้งราคา'),
        };

        $priceBeforeRound = $cost->plus($profitBeforeRound);
        $finalPrice = $priceBeforeRound;
        $roundingApplied = false;

        if ($method !== 'manual' && $roundingUnit !== null && $roundingDirection !== null) {
            $finalPrice = $this->roundToUnit($priceBeforeRound, (string) $roundingUnit, $roundingDirection);
            $roundingApplied = $finalPrice->compareTo($priceBeforeRound) !== 0;
        }

        $profitAmount = $finalPrice->minus($cost)->toScale(2, RoundingMode::HALF_UP);
        $profitPercent = $cost->isZero()
            ? null
            : $profitAmount->dividedBy($cost, 8, RoundingMode::HALF_UP)->multipliedBy('100')->toScale(2, RoundingMode::HALF_UP);

        return [
            'profit_before_round' => $this->money($profitBeforeRound),
            'price_before_round' => $this->money($priceBeforeRound),
            'final_price' => $this->money($finalPrice),
            'profit_amount' => $this->money($profitAmount),
            'profit_percent' => $profitPercent?->__toString(),
            'rounding_applied' => $roundingApplied,
        ];
    }

    public function status(Product $product, ?string $suggestedPrice = null, ?array $configuration = null): string
    {
        if (! $product->active) {
            return 'inactive';
        }

        if ($product->selling_price === null) {
            return 'unpriced';
        }

        if ($product->pricing_reviewed_cost !== null
            && BigDecimal::of((string) $product->pricing_reviewed_cost)
                ->compareTo(BigDecimal::of((string) ($product->cost_price ?? 0))) !== 0) {
            return 'pending_review';
        }

        if (($configuration['pricing_source'] ?? null) === self::CATEGORY_SOURCE) {
            if (($configuration['category_rule_available'] ?? true) === false) {
                return 'pending_review';
            }

            if ($suggestedPrice !== null && $product->selling_price !== null
                && ! $this->sameMoney($product->selling_price, $suggestedPrice)) {
                return 'pending_review';
            }
        }

        return 'normal';
    }

    public function review(Product $product, array $data): array
    {
        return DB::transaction(function () use ($product, $data): array {
            $locked = Product::query()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();
            if (! $locked->active) {
                throw new \DomainException('ไม่สามารถแก้ไขราคาสินค้าที่ไม่ใช้งานได้');
            }
            $source = $data['pricing_method'] === self::CATEGORY_SOURCE
                ? self::CATEGORY_SOURCE
                : ($data['pricing_method'] === 'manual' ? 'fixed' : 'product');
            $preview = $this->calculate($locked->load('category.categoryPricingRule'), null, [
                ...$data,
                'pricing_source' => $source,
            ]);

            if ($preview['final_price'] === null) {
                throw new \DomainException('ไม่สามารถคำนวณราคาได้ เนื่องจากยังไม่มีต้นทุนเฉลี่ยหรือค่าตั้งราคา');
            }

            $oldPrice = $locked->selling_price;
            $oldCost = $locked->pricing_reviewed_cost ?? $locked->cost_price;

            $locked->update([
                'pricing_method' => $data['pricing_method'],
                'pricing_source' => $source,
                'pricing_value' => $data['pricing_value'],
                'rounding_direction' => $data['rounding_direction'] ?? null,
                'rounding_unit' => $data['rounding_unit'] ?? null,
                'selling_price' => $preview['final_price'],
                'pricing_reviewed_cost' => $locked->cost_price,
                'pricing_reviewed_at' => now(),
                'pricing_reviewed_by' => Auth::id(),
            ]);

            ProductPriceHistory::query()->create([
                'product_id' => $locked->id,
                'old_price' => $oldPrice,
                'new_price' => $preview['final_price'],
                'old_average_cost' => $oldCost,
                'average_cost' => $preview['average_cost'],
                'pricing_method' => $preview['pricing_method'],
                'pricing_source' => $preview['pricing_source'],
                'pricing_value' => $preview['pricing_value'],
                'category_pricing_rule_id' => $preview['category_rule']['id'] ?? null,
                'category_id' => $locked->category_id,
                'category_name_snapshot' => $locked->category?->name,
                'category_rule_value' => $preview['category_rule']['pricing_value'] ?? null,
                'rounding_direction' => $preview['rounding_direction'],
                'rounding_unit' => $preview['rounding_unit'],
                'profit_amount' => $preview['profit_amount'],
                'profit_percent' => $preview['profit_percent'],
                'price_before_round' => $preview['price_before_round'],
                'satang_rounded_price' => $preview['final_price'],
                'final_price' => $preview['final_price'],
                'created_from' => 'pricing_review',
                'user_id' => Auth::id(),
                'remark' => 'Pricing review',
            ]);

            return $this->calculate($locked->fresh('category.categoryPricingRule'));
        });
    }

    public function configuration(Product $product, ?array $overrides = null): array
    {
        $source = $overrides['pricing_source']
            ?? (($overrides['pricing_method'] ?? null) === self::CATEGORY_SOURCE ? self::CATEGORY_SOURCE : null)
            ?? $product->pricing_source;

        if ($source === self::CATEGORY_SOURCE) {
            $rule = $product->category?->categoryPricingRule;

            if (! $rule && $product->category_id) {
                $rule = CategoryPricingRule::query()
                    ->where('category_id', $product->category_id)
                    ->where('active', true)
                    ->first();
            }

            if (! $rule) {
                return [
                    'pricing_source' => self::CATEGORY_SOURCE,
                    'pricing_method' => 'percentage',
                    'pricing_value' => null,
                    'rounding_direction' => null,
                    'rounding_unit' => null,
                    'category_rule_available' => false,
                    'category_rule' => null,
                ];
            }

            return [
                'pricing_source' => self::CATEGORY_SOURCE,
                'pricing_method' => $rule->pricing_method,
                'pricing_value' => $this->money($rule->pricing_value),
                'rounding_direction' => $rule->rounding_direction,
                'rounding_unit' => $rule->rounding_unit === null ? null : $this->money($rule->rounding_unit),
                'category_rule_available' => true,
                'category_rule' => [
                    'id' => $rule->id,
                    'pricing_method' => $rule->pricing_method,
                    'pricing_value' => $this->money($rule->pricing_value),
                    'rounding_direction' => $rule->rounding_direction,
                    'rounding_unit' => $rule->rounding_unit === null ? null : $this->money($rule->rounding_unit),
                    'active' => (bool) $rule->active,
                ],
            ];
        }

        $method = $overrides['pricing_method'] ?? $product->pricing_method ?? 'percentage';
        $value = $overrides['pricing_value'] ?? $product->pricing_value;

        if ($value === null && $method === 'percentage') {
            $value = $product->profit_percent ?? 20;
        }

        return [
            'pricing_source' => $source ?? ($method === 'manual' ? 'fixed' : 'product'),
            'pricing_method' => $method,
            'pricing_value' => $value === null ? null : $this->money($value),
            'rounding_direction' => $overrides['rounding_direction'] ?? $product->rounding_direction ?? 'up',
            'rounding_unit' => $overrides['rounding_unit'] ?? $product->rounding_unit ?? '5',
            'category_rule_available' => true,
            'category_rule' => null,
        ];
    }

    public function rollbackPriceHistory(ProductPriceHistory $history): void
    {
        DB::transaction(function () use ($history): void {
            $product = Product::query()->whereKey($history->product_id)->lockForUpdate()->firstOrFail();
            if ((string) $product->selling_price !== (string) $history->new_price) {
                throw new \RuntimeException('ไม่สามารถย้อนราคาได้ เนื่องจากมีการเปลี่ยนแปลงราคาหลังจากรายการนี้แล้ว');
            }
            $current = $product->selling_price;
            $product->update(['selling_price' => $history->old_price]);
            ProductPriceHistory::query()->create([
                'product_id' => $product->id,
                'old_price' => $current,
                'new_price' => $history->old_price,
                'average_cost' => $product->cost_price,
                'final_price' => $history->old_price,
                'created_from' => 'rollback',
                'user_id' => Auth::id(),
                'remark' => 'Rollback from price history #'.$history->id,
            ]);
        });
    }

    private function emptyCalculation(string $method, mixed $value, ?string $direction, mixed $unit): array
    {
        return [
            'profit_before_round' => null,
            'price_before_round' => null,
            'final_price' => null,
            'profit_amount' => null,
            'profit_percent' => null,
            'rounding_applied' => false,
        ];
    }

    private function profitForPrice(string $cost, string $price): array
    {
        $costDecimal = BigDecimal::of($cost);
        $profit = BigDecimal::of($price)->minus($costDecimal)->toScale(2, RoundingMode::HALF_UP);
        $percent = $costDecimal->isZero()
            ? null
            : $profit->dividedBy($costDecimal, 8, RoundingMode::HALF_UP)->multipliedBy('100')->toScale(2, RoundingMode::HALF_UP);

        return [$this->money($profit), $percent?->__toString()];
    }

    private function roundToUnit(BigDecimal $price, string $unit, string $direction): BigDecimal
    {
        $unitDecimal = BigDecimal::of($unit);
        $quotient = $price->dividedBy($unitDecimal, 8, match ($direction) {
            'up' => RoundingMode::CEILING,
            'down' => RoundingMode::FLOOR,
            'nearest' => RoundingMode::HALF_UP,
            default => throw new \InvalidArgumentException('ไม่พบวิธีการปัดราคา'),
        });

        return $quotient->toScale(0, match ($direction) {
            'up' => RoundingMode::CEILING,
            'down' => RoundingMode::FLOOR,
            'nearest' => RoundingMode::HALF_UP,
        })->multipliedBy($unitDecimal);
    }

    private function money(mixed $value): string
    {
        return BigDecimal::of((string) $value)->toScale(2, RoundingMode::HALF_UP)->__toString();
    }

    private function sameMoney(mixed $left, mixed $right): bool
    {
        return BigDecimal::of((string) $left)->compareTo(BigDecimal::of((string) $right)) === 0;
    }
}
