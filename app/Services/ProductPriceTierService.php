<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductPriceTier;
use App\Models\ProductUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductPriceTierService
{
    public function getMatchedTier(
        ProductUnit $productUnit,
        float $qty
    ): ?ProductPriceTier {
        return $productUnit
            ->priceTiers()
            ->where('active', true)
            ->where('min_qty', '<=', $qty)
            ->orderByDesc('min_qty')
            ->first();
    }

    public function getUnitPrice(
        ProductUnit $productUnit,
        float $qty
    ): float {
        $basePrice = (float) $productUnit->selling_price;

        $tier = $this->getMatchedTier(
            $productUnit,
            $qty
        );

        if (!$tier) {
            return $basePrice;
        }

        if (!is_null($tier->fixed_price)) {
            return (float) $tier->fixed_price;
        }

        if ((float) $tier->discount_percent > 0) {
            return round(
                $basePrice * (1 - ((float) $tier->discount_percent / 100)),
                2
            );
        }

        return $basePrice;
    }

    public function calculateTotal(
        ProductUnit $productUnit,
        float $qty
    ): float {
        return $this->getUnitPrice(
            $productUnit,
            $qty
        ) * $qty;
    }

    public function getPricing(
        ProductUnit $productUnit,
        float $qty
    ): array {
        $basePrice = (float) $productUnit->selling_price;

        $tier = $this->getMatchedTier(
            $productUnit,
            $qty
        );

        $unitPrice = $this->getUnitPrice(
            $productUnit,
            $qty
        );

        return [
            'product_unit_id' => $productUnit->id,
            'qty' => $qty,
            'base_price' => $basePrice,
            'unit_price' => $unitPrice,
            'total' => round($unitPrice * $qty, 2),

            'matched' => $tier !== null,
            'tier_id' => $tier?->id,
            'min_qty' => $tier?->min_qty,
            'discount_percent' => (float) ($tier?->discount_percent ?? 0),
            'fixed_price' => $tier?->fixed_price,

            'tier_type' => $tier
                ? ($tier->fixed_price !== null ? 'fixed' : 'discount')
                : 'normal',
        ];
    }
    public function storeTier(
        ProductUnit $productUnit,
        array $validated
    ): ProductPriceTier {
        return ProductPriceTier::create([
            'product_unit_id'   => $productUnit->id,
            'min_qty'           => $validated['min_qty'],
            'discount_percent'  => $validated['discount_percent'] ?? 0,
            'fixed_price'       => $validated['fixed_price'] ?? null,
            'active'            => (bool) ($validated['active'] ?? true),
            'sort_order'        => $validated['min_qty'],
        ]);
    }

    public function updateTier(
        ProductPriceTier $productPriceTier,
        array $validated
    ): void {
        $fixedPrice = $validated['fixed_price'] ?? null;

        if ($fixedPrice === '') {
            $fixedPrice = null;
        }

        $discountPercent = $validated['discount_percent'] ?? 0;

        if ($fixedPrice !== null) {
            $discountPercent = 0;
        } else {
            $fixedPrice = null;
        }

        $productPriceTier->update([
            'min_qty'          => $validated['min_qty'],
            'discount_percent' => $discountPercent,
            'fixed_price'      => $fixedPrice,
            'active'           => (bool) ($validated['active'] ?? true),
            'sort_order'       => $validated['min_qty'],
        ]);
    }
    public function getManagementData(
        Request $request
    ): array {
        $products = Product::with([
            'category',
            'productUnits.priceTiers',
        ])

            ->when(
                $request->filled('search'),
                function ($query) use ($request) {

                    $search = trim($request->search);

                    $query->where(function ($q) use ($search) {

                        $q->where('name', 'ILIKE', "%{$search}%")
                            ->orWhere('barcode', 'ILIKE', "%{$search}%");
                    });
                }
            )

            ->when(
                $request->filled('category'),
                function ($query) use ($request) {

                    $query->where(
                        'category_id',
                        $request->category
                    );
                }
            )

            ->orderBy('name')
            ->get();

        $categories = Category::orderBy('name')->get();

        return [
            'products' => $products,
            'categories' => $categories,

            'summary' => [
                'products' => $products->count(),

                'product_units' => $products
                    ->sum(fn($product) => $product->productUnits->count()),

                'price_tiers' => $products
                    ->sum(fn($product) => $product->productUnits->sum(
                        fn($unit) => $unit->priceTiers->count()
                    )),

                'active_price_tiers' => $products
                    ->sum(fn($product) => $product->productUnits->sum(
                        fn($unit) => $unit->priceTiers
                            ->where('active', true)
                            ->count()
                    )),
            ],
        ];
    }

    public function deleteTier(
        ProductPriceTier $productPriceTier
    ): void {
        $productPriceTier->delete();
    }

    public function getBulkCopyData(): array
    {
        return [
            'categories' => Category::orderBy('name')
                ->get([
                    'id',
                    'name',
                ]),

            'product_units' => ProductUnit::query()
                ->with([
                    'product.category',
                    'unit',
                ])
                ->withCount('priceTiers')
                ->orderBy('product_id')
                ->orderBy('id')
                ->get(),
        ];
    }

    public function bulkCopyTiers(
        ProductUnit $sourceProductUnit,
        array $targetProductUnitIds
    ): void {
        DB::transaction(function () use (
            $sourceProductUnit,
            $targetProductUnitIds
        ) {

            $tiers = $sourceProductUnit
                ->priceTiers()
                ->orderBy('min_qty')
                ->get();

            foreach ($targetProductUnitIds as $targetProductUnitId) {

                if (
                    (int) $targetProductUnitId ===
                    (int) $sourceProductUnit->id
                ) {
                    continue;
                }

                ProductPriceTier::where(
                    'product_unit_id',
                    $targetProductUnitId
                )->delete();

                foreach ($tiers as $tier) {

                    ProductPriceTier::create([
                        'product_unit_id'   => $targetProductUnitId,
                        'min_qty'           => $tier->min_qty,
                        'discount_percent'  => $tier->discount_percent,
                        'fixed_price'       => $tier->fixed_price,
                        'active'            => $tier->active,
                        'sort_order'        => $tier->sort_order,
                    ]);
                }
            }
        });
    }

    public function previewBulkCopy(
        ProductUnit $sourceProductUnit,
        array $targetProductUnitIds
    ): array {

        $tiers = $sourceProductUnit
            ->priceTiers()
            ->orderBy('min_qty')
            ->get();

        $targets = ProductUnit::with([
            'product',
            'unit',
        ])
            ->whereIn('id', $targetProductUnitIds)
            ->get();

        return [
            'source' => $sourceProductUnit,
            'tiers' => $tiers,
            'targets' => $targets,
            'tier_count' => $tiers->count(),
            'target_count' => $targets->count(),
            'copy_count' => $tiers->count() * $targets->count(),
        ];
    }
}
