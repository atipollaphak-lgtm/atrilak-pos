<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class ProductUpdateService
{
    public function __construct(
        private ProductUnitService $productUnitService,
        private StockLockService $stockLockService
    ) {}

    public function update(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data) {
            $lockedProduct = $this->stockLockService->lockProducts([$product->getKey()])
                ->get((int) $product->getKey());
            $oldStock = $lockedProduct->stock_qty;
            $newStock = $data['stock_qty'];
            $oldCostPrice = $lockedProduct->cost_price;
            $oldSellingPrice = $lockedProduct->selling_price;

            $lockedProduct->update([
                'barcode' => $data['barcode'] ?? null,
                'name' => $data['name'],
                'category_id' => $data['category_id'],
                'unit_id' => $data['unit_id'] ?? null,
                'unit' => $lockedProduct->unit ?? '-',
                'cost_price' => $data['cost_price'],
                'selling_price' => $data['selling_price'],
                'stock_qty' => $newStock,
                'minimum_stock' => $data['minimum_stock'],
                'vat_enabled' => $data['vat_enabled'] ?? 0,
                'active' => $data['active'] ?? 1,
                'remark' => $data['remark'] ?? null,
                'product_code' => $data['product_code'] ?? null,
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
