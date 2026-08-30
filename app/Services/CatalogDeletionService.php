<?php

namespace App\Services;

use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductUnit;
use App\Models\Unit;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Applies the catalog deletion policy without ever removing historical
 * business documents. A record with a business reference is deactivated;
 * only an unused record is physically deleted.
 */
class CatalogDeletionService
{
    public function updateDeliveryZone(DeliveryZone $zone, array $attributes): DeliveryZone
    {
        return DB::transaction(function () use ($zone, $attributes): DeliveryZone {
            $this->lockDeliveryZoneState();
            $locked = DeliveryZone::query()->lockForUpdate()->findOrFail($zone->getKey());
            $willBeActive = array_key_exists('active', $attributes)
                ? (bool) $attributes['active']
                : (bool) $locked->active;

            if ($locked->active && ! $willBeActive) {
                $this->assertAnotherActiveDeliveryZoneExists($locked->getKey());
            }

            $locked->update($attributes);

            return $locked->fresh();
        });
    }

    public function deleteDeliveryZone(DeliveryZone $zone): array
    {
        return DB::transaction(function () use ($zone): array {
            $this->lockDeliveryZoneState();
            $locked = DeliveryZone::query()->lockForUpdate()->findOrFail($zone->getKey());

            if ($locked->active) {
                $this->assertAnotherActiveDeliveryZoneExists($locked->getKey());
            }

            $references = $this->deliveryZoneReferences($locked->getKey());
            if ($references > 0) {
                $locked->forceFill(['active' => false])->save();

                return [
                    'action' => 'deactivated',
                    'message' => 'โซนนี้ถูกใช้งานแล้ว จึงปิดใช้งานเพื่อรักษาประวัติเดิม',
                    'references' => $references,
                ];
            }

            $locked->delete();

            return [
                'action' => 'deleted',
                'message' => 'ลบโซนเรียบร้อยแล้ว',
                'references' => 0,
            ];
        });
    }

    public function restoreDeliveryZone(DeliveryZone $zone): DeliveryZone
    {
        return DB::transaction(function () use ($zone): DeliveryZone {
            $this->lockDeliveryZoneState();
            $locked = DeliveryZone::query()->lockForUpdate()->findOrFail($zone->getKey());
            $locked->forceFill(['active' => true])->save();

            return $locked->fresh();
        });
    }

    public function deleteProduct(Product $product): array
    {
        return DB::transaction(function () use ($product): array {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->getKey());
            $references = $this->productReferences($locked->getKey());

            if ($references > 0) {
                $locked->forceFill(['active' => false])->save();

                return [
                    'action' => 'deactivated',
                    'message' => 'สินค้านี้ถูกใช้งานแล้ว จึงปิดใช้งานเพื่อรักษาประวัติเดิม',
                    'references' => $references,
                ];
            }

            $locked->delete();

            return [
                'action' => 'deleted',
                'message' => 'ลบสินค้าเรียบร้อยแล้ว',
                'references' => 0,
            ];
        });
    }

    public function restoreProduct(Product $product): Product
    {
        return DB::transaction(function () use ($product): Product {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->getKey());
            $locked->forceFill(['active' => true])->save();

            return $locked->fresh();
        });
    }

    public function deleteCategory(Category $category): array
    {
        return DB::transaction(function () use ($category): array {
            $locked = Category::query()->lockForUpdate()->findOrFail($category->getKey());
            $references = $this->categoryReferences($locked->getKey());

            if ($references > 0) {
                $locked->forceFill(['active' => false])->save();

                return [
                    'action' => 'deactivated',
                    'message' => 'หมวดหมู่นี้ถูกใช้งานแล้ว จึงปิดใช้งานเพื่อรักษาข้อมูลเดิม',
                    'references' => $references,
                ];
            }

            $locked->delete();

            return [
                'action' => 'deleted',
                'message' => 'ลบหมวดหมู่เรียบร้อยแล้ว',
                'references' => 0,
            ];
        });
    }

    public function restoreCategory(Category $category): Category
    {
        return DB::transaction(function () use ($category): Category {
            $locked = Category::query()->lockForUpdate()->findOrFail($category->getKey());
            $locked->forceFill(['active' => true])->save();

            return $locked->fresh();
        });
    }

    public function deleteUnit(Unit $unit): array
    {
        return DB::transaction(function () use ($unit): array {
            $locked = Unit::query()->lockForUpdate()->findOrFail($unit->getKey());
            $references = $this->referencesInTables($locked->getKey(), [
                ['product_units', 'unit_id'],
                ['products', 'unit_id'],
            ]);

            if ($references > 0) {
                $locked->forceFill(['active' => false])->save();

                return [
                    'action' => 'deactivated',
                    'message' => 'หน่วยนี้ถูกใช้งานแล้ว จึงปิดใช้งานเพื่อรักษาข้อมูลเดิม',
                    'references' => $references,
                ];
            }

            $locked->delete();

            return [
                'action' => 'deleted',
                'message' => 'ลบหน่วยนับเรียบร้อยแล้ว',
                'references' => 0,
            ];
        });
    }

    public function restoreUnit(Unit $unit): Unit
    {
        return DB::transaction(function () use ($unit): Unit {
            $locked = Unit::query()->lockForUpdate()->findOrFail($unit->getKey());
            $locked->forceFill(['active' => true])->save();

            return $locked->fresh();
        });
    }

    public function deleteProductUnit(ProductUnit $productUnit): array
    {
        return DB::transaction(function () use ($productUnit): array {
            Product::query()
                ->lockForUpdate()
                ->findOrFail($productUnit->product_id);
            $lockedUnits = ProductUnit::query()
                ->where('product_id', $productUnit->product_id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $locked = $lockedUnits->firstWhere('id', $productUnit->getKey());
            if ($locked === null) {
                throw (new ModelNotFoundException)->setModel(ProductUnit::class, [$productUnit->getKey()]);
            }

            if ($locked->is_base_unit) {
                throw new DomainException('ไม่สามารถลบหน่วยหลักของสินค้าได้');
            }

            $unitCount = ProductUnit::query()
                ->where('product_id', $locked->product_id)
                ->count();
            if ($unitCount <= 1) {
                throw new DomainException('สินค้าต้องมีอย่างน้อย 1 หน่วย');
            }

            $references = $this->productUnitReferences($locked->getKey());
            if ($references > 0) {
                $locked->forceFill(['active' => false])->save();

                return [
                    'action' => 'deactivated',
                    'message' => 'หน่วยสินค้านี้ถูกใช้งานแล้ว จึงปิดใช้งานเพื่อรักษาประวัติเดิม',
                    'references' => $references,
                ];
            }

            // Barcodes are catalog metadata rather than historical facts. If
            // an unused unit is physically removed, remove its barcodes too
            // so no orphaned barcode remains attached to a deleted unit.
            ProductBarcode::query()
                ->where('product_unit_id', $locked->getKey())
                ->lockForUpdate()
                ->get()
                ->each
                ->delete();
            $locked->delete();

            return [
                'action' => 'deleted',
                'message' => 'ลบหน่วยสินค้าเรียบร้อยแล้ว',
                'references' => 0,
            ];
        });
    }

    public function restoreProductUnit(ProductUnit $productUnit): ProductUnit
    {
        return DB::transaction(function () use ($productUnit): ProductUnit {
            $locked = ProductUnit::query()->lockForUpdate()->findOrFail($productUnit->getKey());
            $locked->forceFill(['active' => true])->save();

            return $locked->fresh();
        });
    }

    public function deleteBarcode(Product $product, ProductBarcode $barcode): array
    {
        return DB::transaction(function () use ($product, $barcode): array {
            Product::query()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();
            if ($barcode->product_unit_id !== null) {
                ProductUnit::query()
                    ->whereKey($barcode->product_unit_id)
                    ->where('product_id', $product->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
            }
            $locked = ProductBarcode::query()
                ->lockForUpdate()
                ->whereKey($barcode->getKey())
                ->where('product_id', $product->getKey())
                ->firstOrFail();

            $wasDefault = (bool) $locked->is_default;
            $productUnitId = $locked->product_unit_id;

            $locked->delete();

            if ($wasDefault) {
                $barcodeScope = ProductBarcode::query()
                    ->where('product_id', $product->getKey());
                if ($productUnitId === null) {
                    $barcodeScope->whereNull('product_unit_id');
                } else {
                    $barcodeScope->where('product_unit_id', $productUnitId);
                }

                $barcodeScope->lockForUpdate()->get()->each(function (ProductBarcode $barcode): void {
                    if ($barcode->is_default) {
                        $barcode->forceFill(['is_default' => false])->save();
                    }
                });

                $replacement = (clone $barcodeScope)
                    ->where('active', true)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->first();
                $replacement?->forceFill(['is_default' => true])->save();
            }

            return [
                'action' => 'deleted',
                'message' => 'ลบ Barcode เรียบร้อยแล้ว',
                'references' => 0,
            ];
        });
    }

    private function deliveryZoneReferences(int $zoneId): int
    {
        return $this->referencesInTables($zoneId, [
            ['customer_delivery_addresses', 'delivery_zone_id'],
            ['sales', 'delivery_zone_id'],
            ['sales', 'pricing_zone_id'],
            ['hold_bills', 'delivery_zone_id'],
            ['hold_bills', 'pricing_zone_id'],
            ['customer_import_rows', 'delivery_zone_id'],
        ]);
    }

    private function assertAnotherActiveDeliveryZoneExists(int $zoneId): void
    {
        if (! DeliveryZone::query()
            ->where('active', true)
            ->where('id', '!=', $zoneId)
            ->lockForUpdate()
            ->exists()) {
            throw new DomainException('ต้องเหลือโซนจัดส่งที่เปิดใช้งานอย่างน้อย 1 โซน');
        }
    }

    private function lockDeliveryZoneState(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::select("select pg_advisory_xact_lock(hashtext('atrilak:delivery-zone-active'))");
        }
    }

    private function productReferences(int $productId): int
    {
        $count = $this->referencesInTables($productId, [
            ['stock_movements', 'product_id'],
            ['sale_items', 'product_id'],
            ['purchase_items', 'product_id'],
            ['quotation_items', 'product_id'],
            ['stock_count_items', 'product_id'],
            ['product_price_histories', 'product_id'],
            ['product_scheduled_prices', 'product_id'],
            ['technician_commission_rules', 'product_id'],
            ['frequent_products', 'product_id'],
            ['hold_bill_items', 'product_id'],
        ]);

        foreach (DB::table('product_units')->where('product_id', $productId)->lockForUpdate()->pluck('id') as $productUnitId) {
            $count += $this->productUnitReferences((int) $productUnitId);
        }

        return $count;
    }

    private function categoryReferences(int $categoryId): int
    {
        return $this->referencesInTables($categoryId, [
            ['products', 'category_id'],
            ['category_pricing_rules', 'category_id'],
            ['technician_commission_rules', 'category_id'],
            ['product_price_histories', 'category_id'],
        ]);
    }

    private function productUnitReferences(int $productUnitId): int
    {
        return $this->referencesInTables($productUnitId, [
            ['sale_items', 'product_unit_id'],
            ['purchase_items', 'product_unit_id'],
            ['quotation_items', 'product_unit_id'],
            ['hold_bill_items', 'product_unit_id'],
            ['product_price_tiers', 'product_unit_id'],
            ['product_unit_promotions', 'product_unit_id'],
        ]);
    }

    /**
     * @param  list<array{0:string,1:string}>  $tables
     */
    private function referencesInTables(int $id, array $tables): int
    {
        $count = 0;
        foreach ($tables as [$table, $column]) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                $query = DB::table($table)->where($column, $id);
                $count += Schema::hasColumn($table, 'id')
                    ? $query->lockForUpdate()->get(['id'])->count()
                    : (int) $query->count();
            }
        }

        return $count;
    }
}
