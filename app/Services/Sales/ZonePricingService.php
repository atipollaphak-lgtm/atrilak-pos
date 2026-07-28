<?php

namespace App\Services\Sales;

use App\Models\DeliveryZone;
use App\Models\Product;
use App\Models\ProductUnit;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class ZonePricingService
{
    public function __construct(
        private readonly SaleDecimalService $decimalService
    ) {}

    public function priceLine(
        array $item,
        Product $product,
        ?ProductUnit $unit,
        ?DeliveryZone $zone,
        bool $pickup
    ): array {
        $qty = BigDecimal::of((string) $item['qty']);
        $basePrice = BigDecimal::of((string) ($unit?->selling_price ?? $product->selling_price ?? 0));

        $tiers = $unit?->relationLoaded('priceTiers')
            ? $unit->priceTiers
            : collect();
        $tier = $tiers
            ?->filter(fn ($candidate): bool => BigDecimal::of((string) $candidate->min_qty)->isLessThanOrEqualTo($qty))
            ->sortBy(fn ($candidate) => BigDecimal::of((string) $candidate->min_qty)->toFloat())
            ->last();

        if ($tier !== null) {
            $basePrice = $tier->fixed_price !== null
                ? BigDecimal::of((string) $tier->fixed_price)
                : $basePrice->multipliedBy(
                    BigDecimal::one()->minus(
                        BigDecimal::of((string) ($tier->discount_percent ?? 0))->dividedBy('100', 8, RoundingMode::HALF_UP)
                    )
                );
        }

        $markup = $pickup || $zone === null
            ? BigDecimal::zero()
            : BigDecimal::of((string) ($zone->price_markup_percent ?? 0));
        $beforeRound = $basePrice->multipliedBy(
            BigDecimal::one()->plus($markup->dividedBy('100', 8, RoundingMode::HALF_UP))
        );
        $zoneIncrement = $pickup || $zone === null
            ? BigDecimal::zero()
            : BigDecimal::of((string) ($zone->rounding_increment ?: '0.25'));
        $rounded = $zoneIncrement->isZero()
            ? $this->roundPrice(
                $beforeRound,
                (string) ($product->rounding_unit ?? '5'),
                (string) ($product->rounding_direction ?? 'up')
            )
            : $this->roundPrice($beforeRound, (string) $zoneIncrement, 'up');

        return [
            'base_unit_price' => $this->decimalService->money($basePrice),
            'zone_markup_percent' => $this->decimalService->money($markup),
            'zone_unit_price_before_rounding' => (string) $beforeRound->toScale(8, RoundingMode::HALF_UP),
            'zone_unit_price' => $this->decimalService->money($rounded),
            'rounding_increment' => $this->decimalService->money($zoneIncrement),
        ];
    }

    public function deliveryFee(mixed $productProfitAfterDiscount, ?DeliveryZone $zone, bool $pickup): string
    {
        if ($pickup || $zone === null) {
            return '0.00';
        }

        return $this->decimalService->nonNegativeDifference(
            $zone->minimum_profit ?? 0,
            $productProfitAfterDiscount
        );
    }

    private function roundPrice(BigDecimal $price, string $unit, string $direction): BigDecimal
    {
        $roundingUnit = BigDecimal::of($unit === '' ? '0.01' : $unit);
        $mode = match ($direction) {
            'down' => RoundingMode::FLOOR,
            'nearest' => RoundingMode::HALF_UP,
            default => RoundingMode::CEILING,
        };

        return $price
            ->dividedBy($roundingUnit, 8, $mode)
            ->toScale(0, $mode)
            ->multipliedBy($roundingUnit);
    }
}
