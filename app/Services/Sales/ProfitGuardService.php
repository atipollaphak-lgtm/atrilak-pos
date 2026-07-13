<?php

namespace App\Services\Sales;

class ProfitGuardService
{
    public function check(array $data, float $productProfit): array
    {
        $deliveryType = $data['delivery_type'] ?? 'delivery';
        $deliveryFee = (float) ($data['delivery_fee'] ?? 0);
        $minimumProfit = (float) ($data['minimum_profit'] ?? 0);
        $deliveryZoneId = $data['delivery_zone_id'] ?? null;

        if ($deliveryType === 'pickup') {
            $deliveryFee = 0;
            $minimumProfit = 0;
        }

        $totalProfit = $productProfit + $deliveryFee;
        $shortAmount = max(0, $minimumProfit - $totalProfit);

        return [
            'passed' => $shortAmount <= 0,
            'delivery_type' => $deliveryType,
            'delivery_zone_id' => $deliveryZoneId,
            'product_profit' => $productProfit,
            'delivery_fee' => $deliveryFee,
            'total_profit' => $totalProfit,
            'minimum_profit' => $minimumProfit,
            'short_amount' => $shortAmount,
            'message' => $shortAmount > 0
                ? 'กำไรของบิลนี้ต่ำกว่ากำไรขั้นต่ำของโซน ขาดอีก ' . number_format($shortAmount, 2) . ' บาท'
                : null,
        ];
    }
}
