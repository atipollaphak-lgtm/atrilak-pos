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

            $lineTotal = $this->decimalService->lineTotal($qty, $price);
            $costPrice = $product->cost_price ?? 0;
            $lineProfit = $useBaseQuantityForCost
                ? $this->decimalService->lineProfitFromBaseQuantity(
                    $qty,
                    $price,
                    $item['base_qty'],
                    $costPrice
                )
                : $this->decimalService->lineProfit($qty, $price, $costPrice);

            $sale->items()->create(array_merge([
                'product_id' => $product->id,
                'product_unit_id' => $productUnitId,
                'conversion_rate_used' => $item['conversion_rate_used'],
                'base_qty' => $item['base_qty'],
                'qty' => $qty,
                'selling_price' => $price,
                'cost_price' => $costPrice,
                'total' => $lineTotal,
                'profit' => $lineProfit,
            ], $snapshots[$index] ?? []));
        }
    }
}
