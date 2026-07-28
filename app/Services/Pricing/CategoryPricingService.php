<?php

namespace App\Services\Pricing;

use App\Models\Category;
use App\Models\CategoryPricingRule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CategoryPricingService
{
    public function list(): array
    {
        return Category::query()
            ->where('active', true)
            ->with('categoryPricingRule')
            ->withCount('products')
            ->orderBy('name')
            ->get()
            ->map(function (Category $category): array {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'total_products' => $category->products_count,
                    'category_products' => $category->products()->where('pricing_source', 'category')->count(),
                    'rule' => $category->categoryPricingRule?->toArray(),
                ];
            })
            ->all();
    }

    public function save(array $data, ?CategoryPricingRule $rule = null): CategoryPricingRule
    {
        return DB::transaction(function () use ($data, $rule): CategoryPricingRule {
            $category = Category::query()->whereKey($data['category_id'])->lockForUpdate()->firstOrFail();
            $rule ??= CategoryPricingRule::query()->where('category_id', $category->id)->lockForUpdate()->first();

            if ($rule && ($data['active'] ?? true) === false) {
                $users = $category->products()->where('pricing_source', 'category')->count();
                if ($users > 0) {
                    throw new \DomainException('ไม่สามารถปิดกฎได้ เนื่องจากมีสินค้าใช้กฎหมวด '.$users.' รายการ');
                }
            }

            $attributes = [
                'category_id' => $category->id,
                'pricing_method' => $data['pricing_method'],
                'pricing_value' => $data['pricing_value'],
                'rounding_direction' => $data['rounding_direction'] ?? null,
                'rounding_unit' => $data['rounding_unit'] ?? null,
                'active' => $data['active'] ?? true,
                'updated_by' => Auth::id(),
            ];

            if ($rule) {
                $rule->update($attributes);
            } else {
                $attributes['created_by'] = Auth::id();
                $rule = CategoryPricingRule::query()->create($attributes);
            }

            return $rule->fresh('category');
        });
    }

    public function disable(CategoryPricingRule $rule): void
    {
        DB::transaction(function () use ($rule): void {
            $locked = CategoryPricingRule::query()->whereKey($rule->getKey())->lockForUpdate()->firstOrFail();
            $users = $locked->category()->firstOrFail()->products()->where('pricing_source', 'category')->count();

            if ($users > 0) {
                throw new \DomainException('ไม่สามารถปิดกฎได้ เนื่องจากมีสินค้าใช้กฎหมวด '.$users.' รายการ');
            }

            $locked->update(['active' => false, 'updated_by' => Auth::id()]);
        });
    }
}
