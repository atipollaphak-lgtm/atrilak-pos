<?php

namespace App\Services;

use App\Models\ProductPriceHistory;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Services\Pricing\AverageCostService;
use App\Services\Pricing\PricingService;
use App\Services\Purchases\PurchaseValidationService;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DomainException;
use Illuminate\Support\Facades\DB;

class PurchaseService
{
    public function __construct(
        private AverageCostService $averageCostService,
        private PricingService $pricingService,
        private StockLockService $stockLockService,
        private PurchaseValidationService $purchaseValidationService
    ) {}

    public function create(array $data, ?int $userId = null): Purchase
    {
        return DB::transaction(function () use ($data, $userId) {
            $items = $this->purchaseValidationService->normalizeItems($data['items'] ?? []);
            $purchaseDate = $this->purchaseValidationService->purchaseDate($data['purchase_date'] ?? null);
            $lockedProducts = $this->stockLockService->lockProducts(
                array_column($items, 'product_id')
            );
            $supplierId = $this->purchaseValidationService->assertCreateReferences(
                $data['supplier_id'] ?? null,
                $lockedProducts
            );

            $purchase = Purchase::query()->create([
                'supplier_id' => $supplierId,
                'purchase_date' => $purchaseDate,
                'total_amount' => $this->itemsTotal($items),
            ]);

            foreach ($items as $item) {
                $product = $lockedProducts->get((int) $item['product_id']);

                if (! $product) {
                    throw new DomainException('ไม่พบสินค้า');
                }

                $qty = $item['qty'];
                $costPrice = $item['cost_price'];
                $lineTotal = $this->lineTotal($qty, $costPrice);

                $purchase->items()->create([
                    'product_id' => $product->id,
                    'qty' => $qty,
                    'cost_price' => $costPrice,
                    'total' => (string) $lineTotal,
                ]);

                $stockBefore = $this->stockQuantity($product->stock_qty);
                $averageCost = $this->averageCostService->calculate(
                    (float) (string) $stockBefore,
                    (float) $product->cost_price,
                    (float) $qty,
                    (float) $costPrice
                );

                $stockAfter = $stockBefore->plus($qty)->toScale(4, RoundingMode::UNNECESSARY);
                $product->stock_qty = (string) $stockAfter;
                $product->cost_price = $averageCost;

                $pricing = $this->pricingService->calculate($product, $averageCost);

                if ($pricing['auto_price_enabled'] && ! $pricing['price_lock'] && $pricing['changed']) {
                    $product->selling_price = $pricing['final_price'];

                    ProductPriceHistory::query()->create([
                        'product_id' => $product->id,
                        'old_price' => $pricing['old_price'],
                        'new_price' => $pricing['final_price'],
                        'average_cost' => $pricing['average_cost'],
                        'profit_percent' => $pricing['profit_percent'],
                        'price_before_round' => $pricing['price_before_round'],
                        'satang_rounded_price' => $pricing['satang_rounded_price'],
                        'final_price' => $pricing['final_price'],
                        'created_from' => 'auto_pricing',
                        'user_id' => $userId,
                        'remark' => 'Auto pricing after purchase',
                    ]);
                }

                $product->save();
                $this->movement($product->id, 'IN', $qty, $stockBefore, $stockAfter, 'purchase', $purchase->id, 'ซื้อเข้า');
            }

            return $purchase;
        });
    }

    public function update(Purchase $purchase, array $data): Purchase
    {
        return DB::transaction(function () use ($purchase, $data) {
            $lockedPurchase = Purchase::query()->whereKey($purchase->getKey())->lockForUpdate()->firstOrFail();
            $lockedPurchase->load('items');
            $newItems = $this->purchaseValidationService->normalizeItems($data['items'] ?? []);
            $purchaseDate = $this->purchaseValidationService->purchaseDate($data['purchase_date'] ?? null);
            $productIds = $lockedPurchase->items->pluck('product_id')
                ->merge(array_column($newItems, 'product_id'))->all();
            $lockedProducts = $this->stockLockService->lockProducts($productIds);
            $supplierId = $this->purchaseValidationService->assertUpdateReferences(
                $lockedPurchase,
                $data['supplier_id'] ?? null,
                $newItems,
                $lockedProducts
            );

            foreach ($lockedPurchase->items as $item) {
                $product = $lockedProducts->get((int) $item->product_id);

                if (! $product) {
                    throw new DomainException('ไม่พบสินค้า');
                }

                $before = $this->stockQuantity($product->stock_qty);
                $itemQty = $this->stockQuantity($item->qty);
                $after = $before->minus($itemQty)->toScale(4, RoundingMode::UNNECESSARY);

                if ($after->isLessThan(0)) {
                    throw new DomainException('ไม่สามารถแก้ไขได้ เพราะสต๊อกสินค้า '.$product->name.' จะติดลบ');
                }

                $product->stock_qty = (string) $after;
                $product->save();
                $this->movement($product->id, 'OUT', $itemQty, $before, $after, 'purchase_edit', $lockedPurchase->id, 'คืนรายการรับเข้าเดิมจากการแก้ไข');
            }

            $lockedPurchase->items()->delete();
            $grandTotal = BigDecimal::zero()->toScale(2);

            foreach ($newItems as $item) {
                $product = $lockedProducts->get((int) $item['product_id']);

                if (! $product) {
                    throw new DomainException('ไม่พบสินค้า');
                }

                $qty = $item['qty'];
                $costPrice = $item['cost_price'];
                $lineTotal = $this->lineTotal($qty, $costPrice);
                $lockedPurchase->items()->create([
                    'product_id' => $product->id,
                    'qty' => $qty,
                    'cost_price' => $costPrice,
                    'total' => (string) $lineTotal,
                ]);

                $before = $this->stockQuantity($product->stock_qty);
                $after = $before->plus($qty)->toScale(4, RoundingMode::UNNECESSARY);
                $product->stock_qty = (string) $after;
                $product->cost_price = $costPrice;
                $product->save();
                $this->movement($product->id, 'IN', $qty, $before, $after, 'purchase_edit', $lockedPurchase->id, 'รับเข้าใหม่จากการแก้ไข');
                $grandTotal = $grandTotal->plus($lineTotal);
            }

            $lockedPurchase->update([
                'supplier_id' => $supplierId,
                'purchase_date' => $purchaseDate,
                'total_amount' => (string) $grandTotal,
            ]);

            return $lockedPurchase;
        });
    }

    public function delete(Purchase $purchase): void
    {
        DB::transaction(function () use ($purchase) {
            $lockedPurchase = Purchase::query()->whereKey($purchase->getKey())->lockForUpdate()->firstOrFail();
            $lockedPurchase->load('items');
            $lockedProducts = $this->stockLockService->lockProducts(
                $lockedPurchase->items->pluck('product_id')->all()
            );

            foreach ($lockedPurchase->items as $item) {
                $product = $lockedProducts->get((int) $item->product_id);

                if (! $product) {
                    throw new DomainException('ไม่พบสินค้า');
                }

                $before = $this->stockQuantity($product->stock_qty);
                $itemQty = $this->stockQuantity($item->qty);
                $after = $before->minus($itemQty)->toScale(4, RoundingMode::UNNECESSARY);

                if ($after->isLessThan(0)) {
                    throw new DomainException('ไม่สามารถลบได้ เพราะสต๊อกสินค้า '.$product->name.' จะติดลบ');
                }

                $product->stock_qty = (string) $after;
                $product->save();
                $this->movement($product->id, 'OUT', $itemQty, $before, $after, 'purchase_delete', $lockedPurchase->id, 'ลบรายการรับเข้า');
            }

            $lockedPurchase->items()->delete();
            $lockedPurchase->delete();
        });
    }

    private function movement(
        int $productId,
        string $type,
        BigDecimal|string $qty,
        BigDecimal|string $before,
        BigDecimal|string $after,
        string $referenceType,
        int $referenceId,
        string $remark
    ): void {
        StockMovement::query()->create([
            'product_id' => $productId,
            'type' => $type,
            'qty' => (string) $qty,
            'stock_before' => (string) $before,
            'stock_after' => (string) $after,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'remark' => $remark,
        ]);
    }

    private function itemsTotal(array $items): string
    {
        $total = BigDecimal::zero()->toScale(2);

        foreach ($items as $item) {
            $total = $total->plus($this->lineTotal($item['qty'], $item['cost_price']));
        }

        return (string) $total;
    }

    private function lineTotal(string $qty, string $costPrice): BigDecimal
    {
        return BigDecimal::of($qty)
            ->multipliedBy($costPrice)
            ->toScale(2, RoundingMode::HALF_UP);
    }

    private function stockQuantity(mixed $value): BigDecimal
    {
        return BigDecimal::of((string) $value)->toScale(4, RoundingMode::UNNECESSARY);
    }
}
