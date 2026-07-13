<?php

namespace App\Services\Pricing;

class AverageCostService
{
    /**
     * คำนวณต้นทุนเฉลี่ย (Weighted Average Cost)
     */
    public function calculate(
        float $oldQty,
        float $oldCost,
        float $receiveQty,
        float $receiveCost
    ): float {

        $oldValue = $oldQty * $oldCost;

        $receiveValue = $receiveQty * $receiveCost;

        $totalQty = $oldQty + $receiveQty;

        if ($totalQty <= 0) {
            return round($receiveCost, 2);
        }

        $averageCost = ($oldValue + $receiveValue) / $totalQty;

        return round($averageCost, 2);
    }
}
