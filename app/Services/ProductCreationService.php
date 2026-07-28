<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class ProductCreationService
{
    public function __construct(
        private ProductNumberService $productNumberService,
        private ProductUnitService $productUnitService,
    ) {}

    public function create(array $data): Product
    {
        return DB::transaction(function () use ($data): Product {
            $category = Category::query()->findOrFail($data['category_id']);
            $numbers = $this->productNumberService->generateForCategory($category);

            $product = Product::query()->create([
                ...$data,
                'pricing_reviewed_cost' => ! empty($data['selling_price'])
                    ? ($data['cost_price'] ?? null)
                    : null,
                ...$numbers,
            ]);

            if (! empty($data['unit_id'])) {
                $this->productUnitService->createOrUpdateBaseUnit($product, [
                    'unit_id' => $data['unit_id'],
                    'purchase_price' => $data['cost_price'] ?? null,
                    'selling_price' => $data['selling_price'] ?? null,
                ]);
            }

            return $product;
        });
    }
}
