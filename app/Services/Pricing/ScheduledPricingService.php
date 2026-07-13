<?php

namespace App\Services\Pricing;

use App\Models\ProductPriceHistory;
use Illuminate\Support\Facades\DB;
use App\Models\ProductScheduledPrice;

class ScheduledPricingService
{
    public function getPendingPrices()
    {
        return ProductScheduledPrice::query()
            ->where('is_applied', false)
            ->whereDate('effective_date', '<=', today())
            ->orderBy('effective_date')
            ->get();
    }
    public function apply(ProductScheduledPrice $schedule): void
    {
        DB::transaction(function () use ($schedule) {

            $product = $schedule->product;

            if (! $product) {
                return;
            }

            if ($product->price_lock) {
                return;
            }

            if (! $product->auto_price_enabled) {
                return;
            }

            ProductPriceHistory::create([
                'product_id'           => $product->id,
                'old_price'            => $product->selling_price,
                'new_price'            => $schedule->selling_price,
                'average_cost'         => $product->cost_price,
                'profit_percent'       => null,
                'price_before_round'   => null,
                'satang_rounded_price' => null,
                'final_price'          => $schedule->selling_price,
                'created_from'         => 'scheduled',
                'user_id'              => null,
                'remark'               => $schedule->remark,
            ]);

            $product->selling_price = $schedule->selling_price;
            $product->save();

           $schedule->is_applied = true;
$schedule->applied_at = now();
$schedule->save();
        });
    }
}
