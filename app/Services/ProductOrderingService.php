<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductOrderingService
{
    /**
     * The caller must already hold the destination Category row lock.
     */
    public function nextSortOrderForLockedCategory(int $categoryId): int
    {
        $maxSortOrder = Product::query()
            ->where('category_id', $categoryId)
            ->max('sort_order');

        return $maxSortOrder === null ? 0 : ((int) $maxSortOrder + 1);
    }

    /**
     * Persist a complete active-product order for one category atomically.
     * Inactive products are intentionally excluded and retain their values.
     */
    public function reorder(Category $category, array $productIds): void
    {
        DB::transaction(function () use ($category, $productIds): void {
            $lockedCategory = Category::query()
                ->whereKey($category->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $currentIds = Product::query()
                ->where('category_id', $lockedCategory->getKey())
                ->where('active', true)
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
            $submittedIds = array_map('intval', $productIds);
            $sortedCurrentIds = $currentIds;
            $sortedSubmittedIds = $submittedIds;
            sort($sortedCurrentIds);
            sort($sortedSubmittedIds);

            if (count($submittedIds) !== count(array_unique($submittedIds))
                || $sortedSubmittedIds !== $sortedCurrentIds) {
                throw ValidationException::withMessages([
                    'product_ids' => 'ต้องส่งลำดับสินค้าที่เปิดใช้งานในหมวดนี้ให้ครบทุกสินค้า',
                ]);
            }

            foreach ($submittedIds as $sortOrder => $productId) {
                Product::query()
                    ->whereKey($productId)
                    ->update(['sort_order' => $sortOrder]);
            }
        });
    }
}
