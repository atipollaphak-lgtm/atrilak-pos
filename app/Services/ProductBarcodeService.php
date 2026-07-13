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
            if (($data['is_default'] ?? false) === true) {

                ProductBarcode::where(
                    'product_unit_id',
                    $productUnit->id
                )->update([
                    'is_default' => false,
                ]);
            }

            return ProductBarcode::create([
                'product_id' => $product->id,
                'product_unit_id' => $productUnit->id,
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
            if (($data['is_default'] ?? false) === true) {

                ProductBarcode::where(
                    'product_unit_id',
                    $productBarcode->product_unit_id
                )
                    ->where('id', '!=', $productBarcode->id)
                    ->update([
                        'is_default' => false,
                    ]);
            }

            $productBarcode->update([
                'barcode' => $data['barcode'],
                'is_default' => $data['is_default'] ?? false,
                'active' => $data['active'] ?? true,
                'sort_order' => $data['sort_order'] ?? $productBarcode->sort_order,
            ]);

            return $productBarcode;
        });
    }

    public function deleteBarcode(ProductBarcode $productBarcode): void
    {
        DB::transaction(function () use ($productBarcode) {
            $productBarcode->delete();
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
}
