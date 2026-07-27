<?php

namespace App\Services\Sales;

class ProfitGuardService
{
    private SaleDecimalService $decimalService;

    public function __construct(?SaleDecimalService $decimalService = null)
    {
        $this->decimalService = $decimalService ?? new SaleDecimalService;
    }

    public function check(array $data, mixed $productProfit): array
    {
        $deliveryType = $data['delivery_type'] ?? 'delivery';
        $deliveryFee = $this->decimalService->money($data['delivery_fee'] ?? 0);
        $minimumProfit = $this->decimalService->money($data['minimum_profit'] ?? 0);
        $deliveryZoneId = $data['delivery_zone_id'] ?? null;

        if ($deliveryType === 'pickup') {
            $deliveryFee = '0.00';
            $minimumProfit = '0.00';
        }

        $productProfit = $this->decimalService->money($productProfit);
        $totalProfit = $this->decimalService->addMoney($productProfit, $deliveryFee);
        $shortAmount = $this->decimalService
            ->nonNegativeDifference($minimumProfit, $totalProfit);
        $passed = ! $this->decimalService->isPositive($shortAmount);

        return [
            'passed' => $passed,
            'delivery_type' => $deliveryType,
            'delivery_zone_id' => $deliveryZoneId,
            'product_profit' => $productProfit,
            'delivery_fee' => $deliveryFee,
            'total_profit' => $totalProfit,
            'minimum_profit' => $minimumProfit,
            'short_amount' => $shortAmount,
            'message' => ! $passed
                ? 'กำไรของบิลนี้ต่ำกว่ากำไรขั้นต่ำของโซน ขาดอีก '.number_format($shortAmount, 2).' บาท'
                : null,
        ];
    }
}
