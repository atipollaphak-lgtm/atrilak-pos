<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductUnit;
use Illuminate\Support\Facades\DB;

class ProductBarcodeService
{
    public function createBarcode(
        Product $product,
        ProductUnit $productUnit,
        array $data
    ): ProductBarcode {
        return DB::transaction(function () use (
            $product,
            $productUnit,
            $data
        ) {
            $this->lockProductAndUnit($product->getKey(), $productUnit->getKey());

            if (($data['is_default'] ?? false) === true) {
                $this->barcodeScope($product->getKey(), $productUnit->getKey())
                    ->update(['is_default' => false]);
            }

            return ProductBarcode::create([
                'product_id' => $product->getKey(),
                'product_unit_id' => $productUnit->getKey(),
                'barcode' => $data['barcode'],
                'is_default' => $data['is_default'] ?? false,
                'active' => $data['active'] ?? true,
                'sort_order' => $data['sort_order'] ?? 10,
            ]);
        });
    }

    public function updateBarcode(
        ProductBarcode $productBarcode,
        array $data
    ): ProductBarcode {
        return DB::transaction(function () use (
            $productBarcode,
            $data
        ) {
            $productId = (int) $productBarcode->product_id;
            $productUnitId = $productBarcode->product_unit_id === null
                ? null
                : (int) $productBarcode->product_unit_id;
            $this->lockProductAndUnit($productId, $productUnitId);
            $lockedBarcode = ProductBarcode::query()
                ->whereKey($productBarcode->getKey())
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->firstOrFail();

            if (($data['is_default'] ?? false) === true) {
                $this->barcodeScope($productId, $productUnitId)
                    ->where('id', '!=', $lockedBarcode->getKey())
                    ->update(['is_default' => false]);
            }

            $lockedBarcode->update([
                'barcode' => $data['barcode'],
                'is_default' => $data['is_default'] ?? false,
                'active' => $data['active'] ?? true,
                'sort_order' => $data['sort_order'] ?? $lockedBarcode->sort_order,
            ]);

            return $lockedBarcode;
        });
    }

    public function deleteBarcode(ProductBarcode $productBarcode): void
    {
        DB::transaction(function () use ($productBarcode): void {
            $productId = (int) $productBarcode->product_id;
            $productUnitId = $productBarcode->product_unit_id === null
                ? null
                : (int) $productBarcode->product_unit_id;
            $this->lockProductAndUnit($productId, $productUnitId);
            $lockedBarcode = ProductBarcode::query()
                ->whereKey($productBarcode->getKey())
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->firstOrFail();
            $wasDefault = (bool) $lockedBarcode->is_default;
            $lockedBarcode->delete();

            if (! $wasDefault) {
                return;
            }

            $scope = $this->barcodeScope($productId, $productUnitId);
            $scope->update(['is_default' => false]);
            $scope->where('active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->first()?->forceFill(['is_default' => true])->save();
        });
    }

    public function getBarcodesForProduct(Product $product)
    {
        return ProductBarcode::with([
            'product',
            'productUnit.unit',
        ])
            ->where('product_id', $product->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    private function lockProductAndUnit(int $productId, ?int $productUnitId): void
    {
        Product::query()->whereKey($productId)->lockForUpdate()->firstOrFail();

        if ($productUnitId !== null) {
            ProductUnit::query()
                ->whereKey($productUnitId)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->firstOrFail();
        }
    }

    private function barcodeScope(int $productId, ?int $productUnitId)
    {
        $query = ProductBarcode::query()->where('product_id', $productId);

        return $productUnitId === null
            ? $query->whereNull('product_unit_id')
            : $query->where('product_unit_id', $productUnitId);
    }
}
