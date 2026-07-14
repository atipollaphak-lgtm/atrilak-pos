<?php

namespace App\Services;

use App\Models\StockCount;
use App\Models\StockCountItem;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockCountService
{
    public function __construct(private StockLockService $stockLockService) {}

    public function create(array $data): StockCount
    {
        return DB::transaction(function () use ($data) {
            $items = array_values(array_filter(
                $data['items'] ?? [],
                fn (array $item) => ! empty($item['product_id'])
            ));
            $productIds = array_map(fn (array $item) => (int) $item['product_id'], $items);

            if (count($productIds) !== count(array_unique($productIds))) {
                throw ValidationException::withMessages([
                    'product_id' => 'สินค้าในรายการตรวจนับต้องไม่ซ้ำกัน',
                ]);
            }

            $lockedProducts = $this->stockLockService->lockProducts($productIds);
            $countDate = $data['count_date'];
            $running = StockCount::query()->whereDate('count_date', $countDate)->count() + 1;
            $countNo = 'SC-'.date('Ymd', strtotime($countDate)).'-'.str_pad($running, 4, '0', STR_PAD_LEFT);
            $stockCount = StockCount::query()->create([
                'count_no' => $countNo,
                'count_date' => $countDate,
                'remark' => $data['remark'] ?? null,
            ]);

            foreach ($items as $item) {
                $product = $lockedProducts->get((int) $item['product_id']);
                $systemQty = (int) $product->stock_qty;
                $actualQty = (int) ($item['actual_qty'] ?? 0);
                $difference = $actualQty - $systemQty;

                StockCountItem::query()->create([
                    'stock_count_id' => $stockCount->id,
                    'product_id' => $product->id,
                    'system_qty' => $systemQty,
                    'actual_qty' => $actualQty,
                    'difference' => $difference,
                ]);

                if ($difference !== 0) {
                    StockMovement::query()->create([
                        'product_id' => $product->id,
                        'type' => 'ADJUST',
                        'qty' => $difference,
                        'stock_before' => $systemQty,
                        'stock_after' => $actualQty,
                        'reference_type' => StockCount::class,
                        'reference_id' => $stockCount->id,
                        'remark' => 'ตรวจนับสต๊อก '.$countNo,
                    ]);

                    $product->stock_qty = $actualQty;
                    $product->save();
                }
            }

            return $stockCount;
        });
    }
}
