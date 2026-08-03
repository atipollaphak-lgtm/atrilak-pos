<?php

namespace App\Services\Sales;

use App\Models\DeliveryZone;
use App\Models\Product;
use App\Models\ProductUnit;
use DomainException;

class SalePriceSnapshotService
{
    public function __construct(
        private readonly SaleDecimalService $decimalService = new SaleDecimalService,
        private readonly ZonePricingService $zonePricingService = new ZonePricingService(new SaleDecimalService),
    ) {}

    public function systemPrice(
        array $item,
        Product $product,
        ?ProductUnit $unit,
        ?DeliveryZone $zone,
        bool $pickup
    ): string {
        $pricing = $this->zonePricingService->priceLine(
            $item,
            $product,
            $unit,
            $zone,
            $pickup
        );

        return $this->decimalService->money($pricing['zone_unit_price']);
    }

    public function snapshot(
        string $systemPrice,
        string $requestedPrice,
        bool $priceWasEdited
    ): array {
        $systemPrice = $this->normalizePositiveMoney($systemPrice, 'system price');
        $requestedPrice = $this->normalizePositiveMoney($requestedPrice, 'selling price');

        return [
            'selling_price' => $priceWasEdited ? $requestedPrice : $systemPrice,
            'original_price' => $priceWasEdited ? $systemPrice : null,
            'price_override_flag' => $priceWasEdited,
        ];
    }

    private function normalizePositiveMoney(mixed $value, string $label): string
    {
        $text = trim((string) $value);

        if (! preg_match('/^\d{1,13}(?:\.\d{1,2})?$/D', $text)) {
            throw new DomainException($label.' must be a positive amount with at most two decimal places');
        }

        $money = $this->decimalService->money($text);

        if (! $this->decimalService->isPositive($money)) {
            throw new DomainException($label.' must be greater than zero');
        }

        return $money;
    }
}
