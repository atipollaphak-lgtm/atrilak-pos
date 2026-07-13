<?php

namespace App\Services\Sales;

use App\Models\Sale;
use App\Models\StockMovement;
use DomainException;
use Illuminate\Support\Collection;

class StockService
{
    public function deductFromSale(
        Sale $sale,
        Collection $lockedProducts,
        string $referenceType = 'sale',
        string $remark = 'ขายออก'
    ): void {
        $sale->loadMissing('items');

        foreach ($sale->items as $item) {

            $product = $lockedProducts->get((int) $item->product_id);

            if (! $product) {
                throw new DomainException('ไม่พบสินค้า');
            }

            $oldStock = $product->stock_qty;
            $newStock = $oldStock - $item->qty;

            if ($newStock < 0) {
                throw new DomainException('สินค้า '.$product->name.' มีสต็อกไม่พอ');
            }

            $product->stock_qty = $newStock;
            $product->save();

            StockMovement::create([
                'product_id' => $product->id,
                'type' => 'OUT',
                'qty' => $item->qty,
                'stock_before' => $oldStock,
                'stock_after' => $newStock,
                'reference_type' => $referenceType,
                'reference_id' => $sale->id,
                'remark' => $remark,
            ]);
        }
    }

    public function restoreFromSale(
        Sale $sale,
        Collection $lockedProducts,
        string $referenceType,
        string $remark
    ): void {
        $sale->loadMissing('items');

        foreach ($sale->items as $item) {
            $product = $lockedProducts->get((int) $item->product_id);

            if (! $product) {
                throw new DomainException('ไม่พบสินค้า');
            }

            $oldStock = $product->stock_qty;
            $newStock = $oldStock + $item->qty;

            $product->stock_qty = $newStock;
            $product->save();

            StockMovement::create([
                'product_id' => $product->id,
                'type' => 'IN',
                'qty' => $item->qty,
                'stock_before' => $oldStock,
                'stock_after' => $newStock,
                'reference_type' => $referenceType,
                'reference_id' => $sale->id,
                'remark' => $remark,
            ]);
        }
    }
}
