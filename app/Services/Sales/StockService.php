<?php

namespace App\Services\Sales;

use App\Models\Sale;
use App\Models\StockMovement;
use Brick\Math\BigDecimal;
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

            $qty = $this->stockQuantity($item);
            $oldStock = BigDecimal::of($product->stock_qty);
            $newStock = $oldStock->minus($qty);

            if ($newStock->isLessThan(BigDecimal::zero())) {
                throw new DomainException('สินค้า '.$product->name.' มีสต็อกไม่พอ');
            }

            $product->stock_qty = (string) $newStock;
            $product->save();

            StockMovement::create([
                'product_id' => $product->id,
                'type' => 'OUT',
                'qty' => (string) $qty,
                'stock_before' => (string) $oldStock,
                'stock_after' => (string) $newStock,
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

            $qty = $this->stockQuantity($item);
            $oldStock = BigDecimal::of($product->stock_qty);
            $newStock = $oldStock->plus($qty);

            $product->stock_qty = (string) $newStock;
            $product->save();

            StockMovement::create([
                'product_id' => $product->id,
                'type' => 'IN',
                'qty' => (string) $qty,
                'stock_before' => (string) $oldStock,
                'stock_after' => (string) $newStock,
                'reference_type' => $referenceType,
                'reference_id' => $sale->id,
                'remark' => $remark,
            ]);
        }
    }

    private function stockQuantity($item): BigDecimal
    {
        return BigDecimal::of((string) ($item->base_qty ?? $item->qty));
    }
}
