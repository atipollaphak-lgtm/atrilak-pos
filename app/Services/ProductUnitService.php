<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductUnit;
use Illuminate\Support\Facades\DB;

class ProductUnitService
{
    /**
     * เพิ่มหน่วยหลักของสินค้า
     */
    public function createOrUpdateBaseUnit(
        Product $product,
        array $data
    ): ProductUnit {

        return DB::transaction(function () use ($product, $data) {

            ProductUnit::where('product_id', $product->id)
                ->where('is_base_unit', true)
                ->update([
                    'is_base_unit' => false,
                ]);

            return ProductUnit::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'unit_id'    => $data['unit_id'],
                ],
                [
                    'conversion_rate'  => 1,
                    'is_base_unit'     => true,
                    'is_purchase_unit' => true,
                    'is_sale_unit'     => true,
                    'purchase_price'   => $data['purchase_price'] ?? null,
                    'selling_price'    => $data['selling_price'] ?? null,
                    'active'           => true,
                    'sort_order'       => 1,
                ]
            );
        });
    }

    public function createOrUpdateUnit(
        Product $product,
        array $data
    ): ProductUnit {

        return DB::transaction(function () use ($product, $data) {

            return ProductUnit::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'unit_id'    => $data['unit_id'],
                ],
                [
                    'conversion_rate'  => $data['conversion_rate'],
                    'is_base_unit'     => false,
                    'is_purchase_unit' => $data['is_purchase_unit'] ?? true,
                    'is_sale_unit'     => $data['is_sale_unit'] ?? true,
                    'purchase_price'   => $data['purchase_price'] ?? null,
                    'selling_price'    => $data['selling_price'] ?? null,
                    'active'           => $data['active'] ?? true,
                    'sort_order'       => $data['sort_order'] ?? 10,
                ]
            );
        });
    }
    public function updateUnit(
        ProductUnit $productUnit,
        array $data
    ): ProductUnit {

        return DB::transaction(function () use ($productUnit, $data) {

            $productUnit->update([
                'conversion_rate'  => $data['conversion_rate'],
                'is_purchase_unit' => $data['is_purchase_unit'] ?? false,
                'is_sale_unit'     => $data['is_sale_unit'] ?? false,
                'purchase_price'   => $data['purchase_price'] ?? null,
                'selling_price'    => $data['selling_price'] ?? null,
                'active'           => $data['active'] ?? true,
            ]);

            return $productUnit;
        });
    }
    public function deleteUnit(ProductUnit $productUnit): void
    {
        DB::transaction(function () use ($productUnit) {

            if ($productUnit->is_base_unit) {
                throw new \Exception('ไม่สามารถลบหน่วยหลักของสินค้าได้');
            }

            $unitCount = ProductUnit::where('product_id', $productUnit->product_id)
                ->count();

            if ($unitCount <= 1) {
                throw new \Exception('สินค้าต้องมีอย่างน้อย 1 หน่วย');
            }

            $productUnit->delete();
        });
    }

    public function getUnitsForProduct(Product $product)
    {
        return ProductUnit::with('unit')
            ->where('product_id', $product->id)
            ->orderByDesc('is_base_unit')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }
}
