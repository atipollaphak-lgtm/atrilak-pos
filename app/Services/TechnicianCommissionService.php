<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\TechnicianCommission;
use App\Models\TechnicianCommissionRule;

class TechnicianCommissionService
{
    public function createFromSale(Sale $sale): ?TechnicianCommission
    {
        if (!$sale->technician_id) {
            return null;
        }

        $sale->load([
            'items.product.category',
        ]);

        $totalCommission = 0;
        $calculationDetails = [];

        foreach ($sale->items as $item) {

            $product = $item->product;

            if (!$product) {
                continue;
            }

            $rule = $this->findRule($product);

            if (!$rule) {
                continue;
            }

            $lineCommission = $this->calculateLineCommission($item, $rule);

            if ($lineCommission <= 0) {
                continue;
            }

            $totalCommission += $lineCommission;

            $calculationDetails[] = [
                'product_name' => $product->name,
                'qty' => $item->qty,
                'line_total' => $item->total,
                'rule_name' => $rule->name,
                'rule_type' => $rule->rule_type,
                'rule_value' => $rule->rule_value,
                'commission' => $lineCommission,
            ];
        }

        if ($totalCommission <= 0) {
            return null;
        }

        return TechnicianCommission::create([
            'technician_id' => $sale->technician_id,
            'sale_id' => $sale->id,
            'commission_date' => $sale->sale_date,
            'sale_total' => $sale->total_amount,
            'commission_rate' => 0,
            'commission_amount' => $totalCommission,
            'status' => 'pending',
            'rule_name' => 'คำนวณตามกฎสินค้า/หมวดสินค้า',
            'calculation_detail' => json_encode($calculationDetails, JSON_UNESCAPED_UNICODE),
            'remark' => 'สร้างอัตโนมัติจากบิลขาย ' . $sale->sale_no,
        ]);
    }

    private function findRule($product): ?TechnicianCommissionRule
    {
        $productRule = TechnicianCommissionRule::where('active', true)
            ->where('product_id', $product->id)
            ->latest()
            ->first();

        if ($productRule) {
            return $productRule;
        }

        if (!$product->category_id) {
            return null;
        }

        return TechnicianCommissionRule::where('active', true)
            ->whereNull('product_id')
            ->where('category_id', $product->category_id)
            ->latest()
            ->first();
    }

    private function calculateLineCommission($item, TechnicianCommissionRule $rule): float
    {
        $qty = (float) $item->qty;
        $lineTotal = (float) $item->total;
        $ruleValue = (float) $rule->rule_value;

        if ($rule->rule_type === 'percent') {
            return round($lineTotal * ($ruleValue / 100), 2);
        }

        if ($rule->rule_type === 'amount') {
            return round($qty * $ruleValue, 2);
        }

        return 0;
    }
}
