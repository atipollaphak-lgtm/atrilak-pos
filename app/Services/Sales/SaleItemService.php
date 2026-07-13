<?php

namespace App\Services\Sales;

use App\Models\Sale;
use App\Models\Product;

class SaleItemService
{
    public function createItems(Sale $sale, array $items): void
    {
        foreach ($items as $item) {

            $product = Product::find($item['product_id']);

            if (!$product) {
                continue;
            }

            $qty = $item['qty'];
            $price = $item['selling_price'];

            $productUnitId = $item['product_unit_id'] ?? null;

            $lineTotal = $qty * $price;
            $costPrice = $product->cost_price ?? 0;
            $lineProfit = ($price - $costPrice) * $qty;

            $sale->items()->create([
    'product_id' => $product->id,
    'product_unit_id' => $productUnitId,

    'qty' => $qty,

    'selling_price' => $price,

    'cost_price' => $costPrice,

    'total' => $lineTotal,

    'profit' => $lineProfit,
]);
        }
    }
}
