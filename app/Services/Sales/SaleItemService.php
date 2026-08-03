<?php

namespace App\Services\Sales;

use App\Models\Sale;
use DomainException;
use Illuminate\Support\Collection;

class SaleItemService
{
    private SaleDecimalService $decimalService;

    public function __construct(?SaleDecimalService $decimalService = null)
    {
        $this->decimalService = $decimalService ?? new SaleDecimalService;
    }

    public function createItems(
        Sale $sale,
        array $items,
        Collection $lockedProducts,
        array $snapshots = []
    ): void {
        $this->createItemsUsingCostQuantity(
            $sale,
            $items,
            $lockedProducts,
            $snapshots,
            false
        );
    }

    public function createItemsForNewSale(
        Sale $sale,
        array $items,
        Collection $lockedProducts,
        array $snapshots = []
    ): void {
        $this->createItemsUsingCostQuantity(
            $sale,
            $items,
            $lockedProducts,
            $snapshots,
            true
        );
    }

    public function attributesForResolvedLine(
        array $item,
        mixed $costPrice,
        array $snapshots = []
    ): array {
        $lineTotal = $this->decimalService->lineTotal(
            $item['qty'],
            $item['selling_price']
        );
        $lineProfit = $this->decimalService->lineProfitFromBaseQuantity(
            $item['qty'],
            $item['selling_price'],
            $item['base_qty'],
            $costPrice
        );

        return array_merge([
            'product_id' => (int) $item['product_id'],
            'product_unit_id' => $item['product_unit_id'] ?? null,
            'conversion_rate_used' => $item['conversion_rate_used'],
            'base_qty' => $item['base_qty'],
            'qty' => $item['qty'],
            'selling_price' => $item['selling_price'],
            'original_price' => $item['original_price'] ?? null,
            'price_override_flag' => (bool) ($item['price_override_flag'] ?? false),
            'cost_price' => $this->decimalService->money($costPrice),
            'total' => $lineTotal,
            'profit' => $lineProfit,
        ], $snapshots);
    }

    private function createItemsUsingCostQuantity(
        Sale $sale,
        array $items,
        Collection $lockedProducts,
        array $snapshots,
        bool $useBaseQuantityForCost
    ): void {
        foreach ($items as $index => $item) {

            $product = $lockedProducts->get((int) $item['product_id']);

            if (! $product) {
                throw new DomainException('ไม่พบสินค้า');
            }

            $qty = $item['qty'];
            $price = $item['selling_price'];

            $productUnitId = $item['product_unit_id'] ?? null;

            $costPrice = $product->cost_price ?? 0;
            $attributes = $useBaseQuantityForCost
                ? $this->attributesForResolvedLine(
                    $item,
                    $costPrice,
                    $snapshots[$index] ?? []
                )
                : array_merge([
                    'product_id' => $product->id,
                    'product_unit_id' => $productUnitId,
                    'conversion_rate_used' => $item['conversion_rate_used'],
                    'base_qty' => $item['base_qty'],
                    'qty' => $qty,
                    'selling_price' => $price,
                    'original_price' => $item['original_price'] ?? null,
                    'price_override_flag' => (bool) ($item['price_override_flag'] ?? false),
                    'cost_price' => $costPrice,
                    'total' => $this->decimalService->lineTotal($qty, $price),
                    'profit' => $this->decimalService->lineProfit($qty, $price, $costPrice),
                ], $snapshots[$index] ?? []);

            $sale->items()->create($attributes);
        }
    }
}
