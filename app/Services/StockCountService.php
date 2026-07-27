<?php

namespace App\Services;

use App\Models\StockCount;
use App\Models\StockCountItem;
use App\Models\StockMovement;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockCountService
{
    public function __construct(
        private StockLockService $stockLockService,
        private StockCountNumberService $stockCountNumberService
    ) {}

    public function create(array $data): StockCount
    {
        return DB::transaction(function () use ($data) {
            $items = array_values($data['items'] ?? []);

            if ($items === []) {
                throw ValidationException::withMessages([
                    'normalized_items' => 'กรุณาเลือกอย่างน้อยหนึ่งรายการสินค้า',
                ]);
            }

            $productIds = array_map(
                fn (array $item) => $this->productId($item['product_id'] ?? null),
                $items
            );

            if (count($productIds) !== count(array_unique($productIds))) {
                throw ValidationException::withMessages([
                    'product_id' => 'สินค้าในรายการตรวจนับต้องไม่ซ้ำกัน',
                ]);
            }

            $lockedProducts = $this->stockLockService->lockProducts($productIds);
            $countDate = $data['count_date'];
            $countNo = $this->stockCountNumberService->generate($countDate);
            $stockCount = StockCount::query()->create([
                'count_no' => $countNo,
                'count_date' => $countDate,
                'remark' => $data['remark'] ?? null,
            ]);

            foreach ($items as $index => $item) {
                $product = $lockedProducts->get($productIds[$index]);
                $systemQty = $this->quantity($product->stock_qty, 'system_qty');
                $actualQty = $this->quantity($item['actual_qty'] ?? null, 'actual_qty');
                $difference = $actualQty->minus($systemQty)->toScale(4, RoundingMode::UNNECESSARY);

                StockCountItem::query()->create([
                    'stock_count_id' => $stockCount->id,
                    'product_id' => $product->id,
                    'system_qty' => (string) $systemQty,
                    'actual_qty' => (string) $actualQty,
                    'difference' => (string) $difference,
                ]);

                if (! $difference->isZero()) {
                    StockMovement::query()->create([
                        'product_id' => $product->id,
                        'type' => 'ADJUST',
                        'qty' => (string) $difference,
                        'stock_before' => (string) $systemQty,
                        'stock_after' => (string) $actualQty,
                        'reference_type' => StockCount::class,
                        'reference_id' => $stockCount->id,
                        'remark' => 'ตรวจนับสต๊อก '.$countNo,
                    ]);

                    $product->stock_qty = (string) $actualQty;
                    $product->save();
                }
            }

            return $stockCount;
        });
    }

    private function productId(mixed $value): int
    {
        $valid = (is_int($value) && $value > 0)
            || (is_string($value) && preg_match('/^\d+$/D', $value) === 1 && (int) $value > 0);

        if (! $valid) {
            throw ValidationException::withMessages(['product_id' => 'ข้อมูลสินค้าไม่ถูกต้อง']);
        }

        return (int) $value;
    }

    private function quantity(mixed $value, string $field): BigDecimal
    {
        try {
            $quantity = BigDecimal::of((string) $value)
                ->toScale(4, RoundingMode::UNNECESSARY);
        } catch (MathException) {
            throw ValidationException::withMessages([
                $field => 'จำนวนตรวจนับไม่ถูกต้องหรือมีทศนิยมเกิน 4 ตำแหน่ง',
            ]);
        }

        if ($quantity->isLessThan(BigDecimal::zero())) {
            throw ValidationException::withMessages([
                $field => 'จำนวนตรวจนับต้องไม่น้อยกว่า 0',
            ]);
        }

        return $quantity;
    }
}
