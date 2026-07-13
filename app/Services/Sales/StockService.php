<?php

namespace App\Services\Sales;

use App\Models\Sale;
use App\Models\StockMovement;

class StockService
{
    public function deductFromSale(Sale $sale): void
    {
        $sale->loadMissing('items.product');

        foreach ($sale->items as $item) {

            $product = $item->product;

            if (!$product) {
                continue;
            }

            $oldStock = $product->stock_qty;
            $newStock = $oldStock - $item->qty;

            $product->stock_qty = $newStock;
            $product->save();

            StockMovement::create([
                'product_id'     => $product->id,
                'type'           => 'OUT',
                'qty'            => $item->qty,
                'stock_before'   => $oldStock,
                'stock_after'    => $newStock,
                'reference_type' => 'sale',
                'reference_id'   => $sale->id,
                'remark'         => 'ขายออก',
            ]);
        }
    }
}
