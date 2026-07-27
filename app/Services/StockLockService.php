<?php

namespace App\Services;

use App\Models\Product;
use Brick\Math\BigDecimal;
use DomainException;
use Illuminate\Support\Collection;

class StockLockService
{
    public function lockProducts(array $productIds): Collection
    {
        $orderedIds = collect($productIds)
            ->filter(fn ($productId) => $productId !== null && $productId !== '')
            ->map(fn ($productId) => (int) $productId)
            ->unique()
            ->sort()
            ->values();

        if ($orderedIds->isEmpty()) {
            return collect();
        }

        $products = Product::query()
            ->whereIn('id', $orderedIds->all())
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($products->count() !== $orderedIds->count()) {
            throw new DomainException('ไม่พบสินค้า');
        }

        return $products;
    }

    public function assertSufficientStock(Collection $lockedProducts, array $items): void
    {
        $requiredByProduct = [];

        foreach ($items as $item) {
            $productId = $item['product_id'] ?? null;
            $qty = $item['base_qty'] ?? $item['qty'] ?? 0;

            if (empty($productId) || empty($qty)) {
                continue;
            }

            $productId = (int) $productId;
            $requiredByProduct[$productId] = isset($requiredByProduct[$productId])
                ? $requiredByProduct[$productId]->plus((string) $qty)
                : BigDecimal::of((string) $qty);
        }

        ksort($requiredByProduct, SORT_NUMERIC);

        foreach ($requiredByProduct as $productId => $requiredQty) {
            $product = $lockedProducts->get($productId);

            if (! $product) {
                throw new DomainException('ไม่พบสินค้า');
            }

            if (BigDecimal::of($product->stock_qty)->isLessThan($requiredQty)) {
                throw new DomainException('สินค้า '.$product->name.' มีสต็อกไม่พอ');
            }
        }
    }

    /**
     * @param  array<int, string>  $requiredBaseQtyByProduct
     */
    public function assertSufficientBaseQuantities(
        Collection $lockedProducts,
        array $requiredBaseQtyByProduct
    ): void {
        ksort($requiredBaseQtyByProduct, SORT_NUMERIC);

        foreach ($requiredBaseQtyByProduct as $productId => $requiredQty) {
            $product = $lockedProducts->get((int) $productId);

            if (! $product) {
                throw new DomainException('à¹„à¸¡à¹ˆà¸žà¸šà¸ªà¸´à¸™à¸„à¹‰à¸²');
            }

            if (BigDecimal::of($product->stock_qty)->isLessThan(
                BigDecimal::of((string) $requiredQty)
            )) {
                throw new DomainException('à¸ªà¸´à¸™à¸„à¹‰à¸² '.$product->name.' à¸¡à¸µà¸ªà¸•à¹‡à¸­à¸à¹„à¸¡à¹ˆà¸žà¸­');
            }
        }
    }
}
