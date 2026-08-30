<?php

namespace App\Services;

use App\Models\Category;
use App\Models\CategoryPricingRule;
use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Models\StockMovement;
use App\Models\Unit;
use DomainException;
use Illuminate\Support\Facades\DB;

class ProductUpdateService
{
    public function __construct(
        private ProductUnitService $productUnitService,
        private StockLockService $stockLockService,
        private ProductOrderingService $productOrderingService
    ) {}

    public function update(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data) {
            $requestedCategoryId = (int) $data['category_id'];
            $originalCategoryId = (int) $product->category_id;
            $categoryChanged = $originalCategoryId !== $requestedCategoryId;

            if ($categoryChanged) {
                $categoryIds = array_values(array_unique([$originalCategoryId, $requestedCategoryId]));
                sort($categoryIds);
                Category::query()
                    ->whereIn('id', $categoryIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
            }

            $requestedCategory = Category::query()
                ->whereKey($requestedCategoryId)
                ->lockForUpdate()
                ->firstOrFail();
            if (! $requestedCategory->active) {
                throw new DomainException('หมวดหมู่ที่เลือกปิดใช้งานแล้ว ไม่สามารถแก้ไขสินค้าได้');
            }

            if (! empty($data['unit_id'])) {
                $requestedUnit = Unit::query()
                    ->whereKey($data['unit_id'])
                    ->lockForUpdate()
                    ->firstOrFail();
                if (! $requestedUnit->active) {
                    throw new DomainException('หน่วยที่เลือกปิดใช้งานแล้ว ไม่สามารถแก้ไขสินค้าได้');
                }
            }

            $lockedProduct = $this->stockLockService->lockProducts([$product->getKey()])
                ->get((int) $product->getKey());
            $oldStock = $lockedProduct->stock_qty;
            $newStock = $data['stock_qty'];
            $oldCostPrice = $lockedProduct->cost_price;
            $oldSellingPrice = $lockedProduct->selling_price;

            if ($lockedProduct->pricing_source === 'category'
                && (int) $lockedProduct->category_id !== $requestedCategoryId
                && ! CategoryPricingRule::query()
                    ->where('category_id', $requestedCategoryId)
                    ->where('active', true)
                    ->exists()) {
                throw new DomainException('หมวดใหม่ยังไม่ได้ตั้งค่าราคา ไม่สามารถย้ายสินค้าที่ใช้กฎหมวดได้');
            }

            $nextSortOrder = $categoryChanged
                ? $this->productOrderingService->nextSortOrderForLockedCategory($requestedCategoryId)
                : $lockedProduct->sort_order;

            $lockedProduct->update([
                'name' => $data['name'],
                'category_id' => $requestedCategoryId,
                'sort_order' => $nextSortOrder,
                'unit_id' => $data['unit_id'] ?? null,
                'unit' => $lockedProduct->unit ?? '-',
                'cost_price' => $data['cost_price'],
                'selling_price' => $data['selling_price'],
                'stock_qty' => $newStock,
                'minimum_stock' => $data['minimum_stock'],
                'vat_enabled' => $data['vat_enabled'] ?? 0,
                'active' => $data['active'] ?? 1,
                'remark' => $data['remark'] ?? null,
                'sku' => $data['sku'] ?? null,
                'image_path' => $data['image_path'] ?? $lockedProduct->image_path,
            ]);

            if (! empty($data['unit_id'])) {
                $this->productUnitService->createOrUpdateBaseUnit($lockedProduct, [
                    'unit_id' => $data['unit_id'],
                    'purchase_price' => $data['cost_price'],
                    'selling_price' => $data['selling_price'],
                ]);
            }

            if ($oldCostPrice != $data['cost_price'] || $oldSellingPrice != $data['selling_price']) {
                ProductPriceHistory::query()->create([
                    'product_id' => $lockedProduct->id,
                    'old_cost_price' => $oldCostPrice,
                    'new_cost_price' => $data['cost_price'],
                    'old_selling_price' => $oldSellingPrice,
                    'new_selling_price' => $data['selling_price'],
                    'remark' => 'แก้ไขราคาสินค้า',
                ]);
            }

            if ($oldStock != $newStock) {
                StockMovement::query()->create([
                    'product_id' => $lockedProduct->id,
                    'type' => 'ADJUST',
                    'qty' => abs($newStock - $oldStock),
                    'stock_before' => $oldStock,
                    'stock_after' => $newStock,
                    'reference_type' => 'adjust',
                    'reference_id' => $lockedProduct->id,
                    'remark' => 'ปรับสต๊อกจากหน้าแก้ไขสินค้า',
                ]);
            }

            return $lockedProduct;
        });
    }
}
