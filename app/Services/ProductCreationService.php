<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use DomainException;
use Illuminate\Support\Facades\DB;

class ProductCreationService
{
    public function __construct(
        private ProductNumberService $productNumberService,
        private ProductUnitService $productUnitService,
        private ProductOrderingService $productOrderingService,
    ) {}

    public function create(array $data): Product
    {
        return DB::transaction(function () use ($data): Product {
            $category = Category::query()
                ->lockForUpdate()
                ->findOrFail($data['category_id']);
            if (! $category->active) {
                throw new DomainException('หมวดหมู่ที่เลือกปิดใช้งานแล้ว ไม่สามารถเพิ่มสินค้าได้');
            }
            if (! empty($data['unit_id'])) {
                $unit = Unit::query()
                    ->lockForUpdate()
                    ->findOrFail($data['unit_id']);
                if (! $unit->active) {
                    throw new DomainException('หน่วยที่เลือกปิดใช้งานแล้ว ไม่สามารถเพิ่มสินค้าได้');
                }
            }
            $numbers = $this->productNumberService->generateForCategory($category);

            $product = Product::query()->create([
                ...$data,
                'pricing_reviewed_cost' => ! empty($data['selling_price'])
                    ? ($data['cost_price'] ?? null)
                    : null,
                ...$numbers,
                'sort_order' => $this->productOrderingService
                    ->nextSortOrderForLockedCategory((int) $category->getKey()),
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
