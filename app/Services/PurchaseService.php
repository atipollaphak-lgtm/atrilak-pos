<?php

namespace App\Services;

use App\Models\ProductPriceHistory;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Services\Pricing\AverageCostService;
use App\Services\Pricing\PricingService;
use DomainException;
use Illuminate\Support\Facades\DB;

class PurchaseService
{
    public function __construct(
        private AverageCostService $averageCostService,
        private PricingService $pricingService,
        private StockLockService $stockLockService
    ) {}

    public function create(array $data, ?int $userId = null): Purchase
    {
        return DB::transaction(function () use ($data, $userId) {
            $items = $data['items'] ?? [];
            $lockedProducts = $this->stockLockService->lockProducts(
                array_column($items, 'product_id')
            );

            $purchase = Purchase::query()->create([
                'supplier_id' => $data['supplier_id'],
                'purchase_date' => $data['purchase_date'],
                'total_amount' => collect($items)->sum(
                    fn (array $item) => $item['qty'] * $item['cost_price']
                ),
            ]);

            foreach ($items as $item) {
                $product = $lockedProducts->get((int) $item['product_id']);

                if (! $product) {
                    throw new DomainException('ไม่พบสินค้า');
                }

                $qty = $item['qty'];
                $costPrice = $item['cost_price'];
                $lineTotal = $qty * $costPrice;

                $purchase->items()->create([
                    'product_id' => $product->id,
                    'qty' => $qty,
                    'cost_price' => $costPrice,
                    'total' => $lineTotal,
                ]);

                $stockBefore = $product->stock_qty;
                $averageCost = $this->averageCostService->calculate(
                    (float) $product->stock_qty,
                    (float) $product->cost_price,
                    (float) $qty,
                    (float) $costPrice
                );

                $product->stock_qty += $qty;
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
                $this->movement($product->id, 'IN', $qty, $stockBefore, $product->stock_qty, 'purchase', $purchase->id, 'ซื้อเข้า');
            }

            return $purchase;
        });
    }

    public function update(Purchase $purchase, array $data): Purchase
    {
        return DB::transaction(function () use ($purchase, $data) {
            $lockedPurchase = Purchase::query()->whereKey($purchase->getKey())->lockForUpdate()->firstOrFail();
            $lockedPurchase->load('items');
            $newItems = $data['items'] ?? [];
            $productIds = $lockedPurchase->items->pluck('product_id')
                ->merge(array_column($newItems, 'product_id'))->all();
            $lockedProducts = $this->stockLockService->lockProducts($productIds);

            foreach ($lockedPurchase->items as $item) {
                $product = $lockedProducts->get((int) $item->product_id);

                if (! $product) {
                    throw new DomainException('ไม่พบสินค้า');
                }

                $before = $product->stock_qty;
                $after = $before - $item->qty;

                if ($after < 0) {
                    throw new DomainException('ไม่สามารถแก้ไขได้ เพราะสต๊อกสินค้า '.$product->name.' จะติดลบ');
                }

                $product->stock_qty = $after;
                $product->save();
                $this->movement($product->id, 'OUT', $item->qty, $before, $after, 'purchase_edit', $lockedPurchase->id, 'คืนรายการรับเข้าเดิมจากการแก้ไข');
            }

            $lockedPurchase->items()->delete();
            $grandTotal = 0;

            foreach ($newItems as $item) {
                $product = $lockedProducts->get((int) $item['product_id']);

                if (! $product) {
                    throw new DomainException('ไม่พบสินค้า');
                }

                $qty = $item['qty'];
                $costPrice = $item['cost_price'];
                $lineTotal = $qty * $costPrice;
                $lockedPurchase->items()->create([
                    'product_id' => $product->id,
                    'qty' => $qty,
                    'cost_price' => $costPrice,
                    'total' => $lineTotal,
                ]);

                $before = $product->stock_qty;
                $after = $before + $qty;
                $product->stock_qty = $after;
                $product->cost_price = $costPrice;
                $product->save();
                $this->movement($product->id, 'IN', $qty, $before, $after, 'purchase_edit', $lockedPurchase->id, 'รับเข้าใหม่จากการแก้ไข');
                $grandTotal += $lineTotal;
            }

            $lockedPurchase->update([
                'supplier_id' => $data['supplier_id'],
                'purchase_date' => $data['purchase_date'],
                'total_amount' => $grandTotal,
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

                $before = $product->stock_qty;
                $after = $before - $item->qty;

                if ($after < 0) {
                    throw new DomainException('ไม่สามารถลบได้ เพราะสต๊อกสินค้า '.$product->name.' จะติดลบ');
                }

                $product->stock_qty = $after;
                $product->save();
                $this->movement($product->id, 'OUT', $item->qty, $before, $after, 'purchase_delete', $lockedPurchase->id, 'ลบรายการรับเข้า');
            }

            $lockedPurchase->items()->delete();
            $lockedPurchase->delete();
        });
    }

    private function movement(
        int $productId,
        string $type,
        float|int|string $qty,
        float|int|string $before,
        float|int|string $after,
        string $referenceType,
        int $referenceId,
        string $remark
    ): void {
        StockMovement::query()->create([
            'product_id' => $productId,
            'type' => $type,
            'qty' => $qty,
            'stock_before' => $before,
            'stock_after' => $after,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'remark' => $remark,
        ]);
    }
}
