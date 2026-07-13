<?php

namespace App\Services\Pricing;

use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Models\PricingSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PricingService
{
    public function calculate(Product $product, ?float $averageCost = null): array
    {
        $averageCost = $averageCost ?? (float) $product->cost_price;

        $rules = $this->resolveRules($product);

        $profitPercent = (float) $rules['profit_percent'];
        $priceBeforeRound = $averageCost + ($averageCost * ($profitPercent / 100));

        $satangRoundedPrice = $this->roundSatang(
            $priceBeforeRound,
            $rules['satang_rounding_mode']
        );

        $finalPrice = $this->roundBaht(
            $satangRoundedPrice,
            $rules['baht_rounding_mode']
        );

        return [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'average_cost' => round($averageCost, 2),
            'profit_percent' => $profitPercent,
            'price_before_round' => round($priceBeforeRound, 2),
            'satang_rounding_mode' => $rules['satang_rounding_mode'],
            'satang_rounded_price' => round($satangRoundedPrice, 2),
            'baht_rounding_mode' => $rules['baht_rounding_mode'],
            'final_price' => round($finalPrice, 2),
            'old_price' => round((float) $product->selling_price, 2),
            'changed' => round((float) $product->selling_price, 2) !== round($finalPrice, 2),
            'price_lock' => (bool) ($product->price_lock ?? false),
            'auto_price_enabled' => (bool) ($product->auto_price_enabled ?? true),
        ];
    }

    public function updateProductPricingSettings(Product $product, array $validated): void
    {
        DB::transaction(function () use ($product, $validated) {
            $product->update([
                'auto_price_enabled' => (bool) ($validated['auto_price_enabled'] ?? false),
                'price_lock' => (bool) ($validated['price_lock'] ?? false),
                'profit_percent' => $validated['profit_percent'] ?? null,
                'satang_rounding_mode' => $validated['satang_rounding_mode'] ?? null,
                'baht_rounding_mode' => $validated['baht_rounding_mode'] ?? null,
            ]);
        });
    }

    public function applyProductPrice(Product $product): bool
    {
        return DB::transaction(function () use ($product) {
            $preview = $this->calculate($product);

            if ($preview['price_lock']) {
                return false;
            }

            if (! $preview['changed']) {
                return false;
            }

            $oldPrice = $product->selling_price;

            $product->update([
                'selling_price' => $preview['final_price'],
            ]);

            ProductPriceHistory::create([
                'product_id' => $product->id,
                'old_price' => $oldPrice,
                'new_price' => $preview['final_price'],
                'average_cost' => $preview['average_cost'],
                'profit_percent' => $preview['profit_percent'],
                'price_before_round' => $preview['price_before_round'],
                'satang_rounded_price' => $preview['satang_rounded_price'],
                'final_price' => $preview['final_price'],
                'created_from' => 'manual_apply',
                'user_id' => Auth::id(),
                'remark' => 'Manual Apply from Pricing Dashboard',
            ]);

            return true;
        });
    }

    public function recalculateAllProducts(): int
    {
        return DB::transaction(function () {
            $count = 0;

            Product::query()
                ->with('category')
                ->orderBy('id')
                ->chunkById(100, function ($products) use (&$count) {
                    foreach ($products as $product) {
                        $this->calculate($product);
                        $count++;
                    }
                });

            return $count;
        });
    }

    public function applyAllChangedPrices(): int
    {
        return DB::transaction(function () {
            $count = 0;

            Product::query()
                ->with('category')
                ->orderBy('id')
                ->chunkById(100, function ($products) use (&$count) {
                    foreach ($products as $product) {
                        $preview = $this->calculate($product);

                        if ($preview['price_lock']) {
                            continue;
                        }

                        if (! $preview['auto_price_enabled']) {
                            continue;
                        }

                        if (! $preview['changed']) {
                            continue;
                        }

                        $oldPrice = $product->selling_price;

                        $product->update([
                            'selling_price' => $preview['final_price'],
                        ]);

                        ProductPriceHistory::create([
                            'product_id' => $product->id,
                            'old_price' => $oldPrice,
                            'new_price' => $preview['final_price'],
                            'average_cost' => $preview['average_cost'],
                            'profit_percent' => $preview['profit_percent'],
                            'price_before_round' => $preview['price_before_round'],
                            'satang_rounded_price' => $preview['satang_rounded_price'],
                            'final_price' => $preview['final_price'],
                            'created_from' => 'bulk_apply',
                            'user_id' => Auth::id(),
                            'remark' => 'Bulk Apply from Pricing Dashboard',
                        ]);

                        $count++;
                    }
                });

            return $count;
        });
    }

    public function previewAllChanges(): array
    {
        $summary = [
            'total' => 0,
            'changed' => 0,
            'locked' => 0,
            'auto_off' => 0,
            'ready_to_apply' => 0,
        ];

        Product::query()
            ->with('category')
            ->orderBy('id')
            ->chunkById(100, function ($products) use (&$summary) {

                foreach ($products as $product) {

                    $summary['total']++;

                    $preview = $this->calculate($product);

                    if ($preview['changed']) {
                        $summary['changed']++;
                    }

                    if ($preview['price_lock']) {
                        $summary['locked']++;
                    }

                    if (! $preview['auto_price_enabled']) {
                        $summary['auto_off']++;
                    }

                    if (
                        $preview['changed']
                        && ! $preview['price_lock']
                        && $preview['auto_price_enabled']
                    ) {
                        $summary['ready_to_apply']++;
                    }
                }
            });

        return $summary;
    }

    public function getPriceHistories(array $filters = [])
    {
        return ProductPriceHistory::query()
            ->with([
                'product',
                'user',
            ])
            ->when(! empty($filters['from']), function ($query) use ($filters) {
                $query->whereDate('created_at', '>=', $filters['from']);
            })
            ->when(! empty($filters['to']), function ($query) use ($filters) {
                $query->whereDate('created_at', '<=', $filters['to']);
            })
            ->when(! empty($filters['user']), function ($query) use ($filters) {
                $query->whereHas('user', function ($userQuery) use ($filters) {
                    $userQuery->where('name', 'like', '%' . $filters['user'] . '%');
                });
            })
            ->when(! empty($filters['product']), function ($query) use ($filters) {
                $query->whereHas('product', function ($productQuery) use ($filters) {
                    $productQuery->where('name', 'like', '%' . $filters['product'] . '%');
                });
            })
            ->latest()
            ->paginate(20);
    }

    public function rollbackPriceHistory(ProductPriceHistory $history): void
    {
        DB::transaction(function () use ($history) {

            $product = $history->product;

            if (! $product) {
                return;
            }

            if ((float) $product->selling_price !== (float) $history->new_price) {

                throw new \RuntimeException(
                    'ไม่สามารถ Rollback ได้ เนื่องจากมีการเปลี่ยนแปลงราคาหลังจากรายการนี้แล้ว'
                );
            }

            $currentPrice = $product->selling_price;

            $product->update([
                'selling_price' => $history->old_price,
            ]);

            ProductPriceHistory::create([
                'product_id' => $product->id,
                'old_price' => $currentPrice,
                'new_price' => $history->old_price,
                'average_cost' => $history->average_cost,
                'profit_percent' => $history->profit_percent,
                'price_before_round' => $history->price_before_round,
                'satang_rounded_price' => $history->satang_rounded_price,
                'final_price' => $history->old_price,
                'created_from' => 'rollback',
                'user_id' => Auth::id(),
                'remark' => 'Rollback from price history #' . $history->id,
            ]);
        });
    }

    private function resolveRules(Product $product): array
    {
        $setting = PricingSetting::query()->first();

        $category = $product->category;

        return [
            'profit_percent' =>
            $product->profit_percent
                ?? $category?->profit_percent
                ?? $setting?->default_profit_percent
                ?? 20,

            'satang_rounding_mode' =>
            $product->satang_rounding_mode
                ?? $category?->satang_rounding_mode
                ?? $setting?->default_satang_rounding_mode
                ?? 'ceil_satang_50',

            'baht_rounding_mode' =>
            $product->baht_rounding_mode
                ?? $category?->baht_rounding_mode
                ?? $setting?->default_baht_rounding_mode
                ?? 'ceil_5',
        ];
    }

    public function previewCategory(int $categoryId): array
    {
        $items = [];

        Product::query()
            ->with('category')
            ->where('category_id', $categoryId)
            ->orderBy('name')
            ->chunkById(100, function ($products) use (&$items) {

                foreach ($products as $product) {
                    $items[] = $this->calculate($product);
                }
            });

        return $items;
    }

    public function previewCategorySummary(int $categoryId): array
    {
        $summary = [
            'total' => 0,
            'changed' => 0,
            'locked' => 0,
            'auto_off' => 0,
            'ready_to_apply' => 0,
        ];

        foreach ($this->previewCategory($categoryId) as $preview) {
            $summary['total']++;

            if ($preview['changed']) {
                $summary['changed']++;
            }

            if ($preview['price_lock']) {
                $summary['locked']++;
            }

            if (! $preview['auto_price_enabled']) {
                $summary['auto_off']++;
            }

            if (
                $preview['changed']
                && ! $preview['price_lock']
                && $preview['auto_price_enabled']
            ) {
                $summary['ready_to_apply']++;
            }
        }

        return $summary;
    }

    public function applyCategoryPrices(int $categoryId): array
    {
        return DB::transaction(function () use ($categoryId) {

            $result = [
                'updated' => 0,
                'skipped_locked' => 0,
                'skipped_auto_off' => 0,
                'skipped_no_change' => 0,
                'failed' => 0,
            ];

            Product::query()
                ->with('category')
                ->where('category_id', $categoryId)
                ->orderBy('id')
                ->chunkById(100, function ($products) use (&$result) {

                    foreach ($products as $product) {
                        $preview = $this->calculate($product);

                        if ($preview['price_lock']) {
                            $result['skipped_locked']++;
                            continue;
                        }

                        if (! $preview['auto_price_enabled']) {
                            $result['skipped_auto_off']++;
                            continue;
                        }

                        if (! $preview['changed']) {
                            $result['skipped_no_change']++;
                            continue;
                        }

                        $oldPrice = $product->selling_price;

                        $product->update([
                            'selling_price' => $preview['final_price'],
                        ]);

                        ProductPriceHistory::create([
                            'product_id' => $product->id,
                            'old_price' => $oldPrice,
                            'new_price' => $preview['final_price'],
                            'average_cost' => $preview['average_cost'],
                            'profit_percent' => $preview['profit_percent'],
                            'price_before_round' => $preview['price_before_round'],
                            'satang_rounded_price' => $preview['satang_rounded_price'],
                            'final_price' => $preview['final_price'],
                            'created_from' => 'category_bulk',
                            'user_id' => Auth::id(),
                            'remark' => 'Category Bulk Apply from Pricing Dashboard',
                        ]);

                        $result['updated']++;
                    }
                });

            return $result;
        });
    }

    private function roundSatang(float $price, ?string $mode): float
    {
        return match ($mode) {
            'none' => $price,
            'ceil_satang_10' => ceil($price * 10) / 10,
            'ceil_satang_25' => ceil($price * 4) / 4,
            'ceil_satang_50' => ceil($price * 2) / 2,
            default => ceil($price * 2) / 2,
        };
    }

    private function roundBaht(float $price, ?string $mode): float
    {
        return match ($mode) {
            'none' => $price,
            'ceil_baht' => ceil($price),
            'ceil_5' => ceil($price / 5) * 5,
            'ceil_10' => ceil($price / 10) * 10,
            'ceil_25' => ceil($price / 25) * 25,
            'ceil_50' => ceil($price / 50) * 50,
            default => ceil($price / 5) * 5,
        };
    }
}
