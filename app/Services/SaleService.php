<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\Product;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\CustomerDeliveryAddress;
use Illuminate\Support\Facades\DB;
use App\Services\Sales\SaleNumberService;
use App\Services\Sales\SaleItemService;
use App\Services\Sales\StockService;
use App\Services\Sales\CommissionService;
use App\Services\Sales\ProfitGuardService;
use DomainException;

class SaleService
{
    protected SaleNumberService $saleNumberService;
    protected SaleItemService $saleItemService;
    protected StockService $stockService;
    protected CommissionService $commissionService;
    protected ProfitGuardService $profitGuardService;

    public function __construct(
        SaleNumberService $saleNumberService,
        SaleItemService $saleItemService,
        StockService $stockService,
        CommissionService $commissionService,
        ProfitGuardService $profitGuardService
    ) {
        $this->saleNumberService = $saleNumberService;
        $this->saleItemService = $saleItemService;
        $this->stockService = $stockService;
$this->commissionService = $commissionService;
$this->profitGuardService = $profitGuardService;
    }
    public function createSale(array $data)
    {
        return DB::transaction(function () use ($data) {

            $saleDate = $data['sale_date'];

            $saleNo = $this->saleNumberService
                ->generate($saleDate);

            $grandTotal = $data['grand_total'] ?? 0;
            $deliveryType = $data['delivery_type'] ?? 'delivery';
            $discount = $data['discount'] ?? 0;

            $deliveryFee = 0;
            $minimumProfit = 0;
            $deliveryZoneId = null;

            if ($deliveryType === 'delivery') {
                $address = CustomerDeliveryAddress::with('deliveryZone')
                    ->find($data['customer_delivery_address_id'] ?? null);

                if ($address && $address->deliveryZone) {
                    $deliveryFee = (float) $address->deliveryZone->base_delivery_fee;
                    $minimumProfit = (float) $address->deliveryZone->minimum_profit;
                    $deliveryZoneId = $address->deliveryZone->id;
                }
            } else {
                $deliveryFee = 0;
            }

            $netTotal = $grandTotal + $deliveryFee - $discount;

            $sale = new Sale();

            $sale->sale_no = $saleNo;
            $sale->customer_id = $data['customer_id'] ?? null;
            $sale->customer_delivery_address_id =
                $data['customer_delivery_address_id'] ?? null;
            $sale->technician_id = $data['technician_id'] ?? null;
            $sale->sale_date = $saleDate;
            $sale->total_amount = $netTotal;
            $sale->delivery_fee = $deliveryFee;
            $sale->delivery_type = $deliveryType;
            $sale->discount = $discount;

            $sale->save();

            $this->saleItemService
                ->createItems($sale, $data['items']);

            $productProfit = $sale->items()->sum('profit');

            $profitGuardResult = $this->profitGuardService->check(
                [
                    'delivery_type' => $deliveryType,
                    'delivery_fee' => $deliveryFee,
                    'delivery_zone_id' => $deliveryZoneId,
                    'minimum_profit' => $minimumProfit,
                ],
                $productProfit
            );


            if (!$profitGuardResult['passed']) {
                throw new \Exception(
                    $profitGuardResult['message']
                );
            }

            $this->stockService
                ->deductFromSale($sale);

            $this->commissionService
                ->createFromSale($sale);

            return $sale;
        });
    }

    public function updateSale(Sale $sale, array $data): Sale
    {
        return DB::transaction(function () use ($sale, $data) {
            $sale->loadMissing('items');

            foreach ($sale->items as $item) {
                $product = $item->product;

                if (! $product) {
                    continue;
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
                    'reference_type' => 'sale_edit',
                    'reference_id' => $sale->id,
                    'remark' => 'คืนสต๊อกจากการแก้ไขบิล '.$sale->sale_no,
                ]);
            }

            $sale->items()->delete();

            foreach ($data['product_id'] as $index => $productId) {
                $qty = $data['qty'][$index] ?? 0;

                if (empty($productId) || empty($qty)) {
                    continue;
                }

                $product = Product::find($productId);

                if (! $product) {
                    throw new DomainException('ไม่พบสินค้า');
                }

                if ($product->stock_qty < $qty) {
                    throw new DomainException('สินค้า '.$product->name.' มีสต็อกไม่พอ');
                }
            }

            $grandTotal = 0;

            foreach ($data['product_id'] as $index => $productId) {
                $qty = $data['qty'][$index] ?? 0;
                $price = $data['selling_price'][$index] ?? 0;

                if (empty($productId) || empty($qty) || empty($price)) {
                    continue;
                }

                $product = Product::find($productId);
                $lineTotal = $qty * $price;
                $costPrice = $product->cost_price ?? 0;
                $lineProfit = ($price - $costPrice) * $qty;

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $productId,
                    'qty' => $qty,
                    'selling_price' => $price,
                    'cost_price' => $costPrice,
                    'total' => $lineTotal,
                    'profit' => $lineProfit,
                ]);

                $oldStock = $product->stock_qty;
                $newStock = $oldStock - $qty;

                $product->stock_qty = $newStock;
                $product->save();

                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => 'OUT',
                    'qty' => $qty,
                    'stock_before' => $oldStock,
                    'stock_after' => $newStock,
                    'reference_type' => 'sale_edit',
                    'reference_id' => $sale->id,
                    'remark' => 'ขายออกจากการแก้ไขบิล '.$sale->sale_no,
                ]);

                $grandTotal += $lineTotal;
            }

            $deliveryFee = $data['delivery_fee'] ?? 0;
            $discount = $data['discount'] ?? 0;
            $netTotal = $grandTotal + $deliveryFee - $discount;

            $sale->update([
                'customer_id' => $data['customer_id'] ?? null,
                'sale_date' => $data['sale_date'],
                'total_amount' => $netTotal,
                'delivery_fee' => $deliveryFee,
                'discount' => $discount,
            ]);

            return $sale;
        });
    }

    public function deleteSale(Sale $sale): void
    {
        DB::transaction(function () use ($sale) {
            $sale->load('items.product');

            foreach ($sale->items as $item) {
                $product = $item->product;

                if (! $product) {
                    continue;
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
                    'reference_type' => 'sale_delete',
                    'reference_id' => $sale->id,
                    'remark' => 'คืนสต๊อกจากการลบบิล '.$sale->sale_no,
                ]);
            }

            $sale->items()->delete();
            $sale->delete();
        });
    }
}
