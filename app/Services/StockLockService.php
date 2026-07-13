<?php

namespace App\Services;

use App\Models\Product;
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
            $qty = $item['qty'] ?? 0;

            if (empty($productId) || empty($qty)) {
                continue;
            }

            $productId = (int) $productId;
            $requiredByProduct[$productId] = ($requiredByProduct[$productId] ?? 0) + $qty;
        }

        ksort($requiredByProduct, SORT_NUMERIC);

        foreach ($requiredByProduct as $productId => $requiredQty) {
            $product = $lockedProducts->get($productId);

            if (! $product) {
                throw new DomainException('ไม่พบสินค้า');
            }

            if ($product->stock_qty < $requiredQty) {
                throw new DomainException('สินค้า '.$product->name.' มีสต็อกไม่พอ');
            }
        }
    }
}
