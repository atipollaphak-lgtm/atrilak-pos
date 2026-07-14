<?php

namespace App\Services;

use App\Models\CustomerDeliveryAddress;
use App\Models\Quotation;
use App\Models\Sale;
use App\Services\Sales\CommissionService;
use App\Services\Sales\ProductUnitConversionService;
use App\Services\Sales\ProfitGuardService;
use App\Services\Sales\SaleItemService;
use App\Services\Sales\SaleNumberService;
use App\Services\Sales\SaleValidationService;
use App\Services\Sales\StockService;
use DomainException;
use Illuminate\Support\Facades\DB;

class SaleService
{
    protected SaleNumberService $saleNumberService;

    protected SaleItemService $saleItemService;

    protected StockService $stockService;

    protected CommissionService $commissionService;

    protected ProfitGuardService $profitGuardService;

    protected StockLockService $stockLockService;

    protected ProductUnitConversionService $productUnitConversionService;

    protected SaleValidationService $saleValidationService;

    public function __construct(
        SaleNumberService $saleNumberService,
        SaleItemService $saleItemService,
        StockService $stockService,
        CommissionService $commissionService,
        ProfitGuardService $profitGuardService,
        StockLockService $stockLockService,
        ProductUnitConversionService $productUnitConversionService,
        SaleValidationService $saleValidationService
    ) {
        $this->saleNumberService = $saleNumberService;
        $this->saleItemService = $saleItemService;
        $this->stockService = $stockService;
        $this->commissionService = $commissionService;
        $this->profitGuardService = $profitGuardService;
        $this->stockLockService = $stockLockService;
        $this->productUnitConversionService = $productUnitConversionService;
        $this->saleValidationService = $saleValidationService;
    }

    public function createSale(array $data)
    {
        return DB::transaction(function () use ($data) {

            $items = $data['items'] ?? [];
            $this->saleValidationService->assertValidItems($items);
            $this->saleValidationService->assertDeliveryAddressBelongsToCustomer(
                $data['customer_delivery_address_id'] ?? null,
                $data['customer_id'] ?? null
            );
            $lockedProducts = $this->stockLockService->lockProducts(
                array_column($items, 'product_id')
            );
            $items = $this->productUnitConversionService->resolveItems($items);
            $this->stockLockService->assertSufficientStock($lockedProducts, $items);

            $saleDate = $data['sale_date'];

            $saleNo = $this->saleNumberService
                ->generate($saleDate);

            $grandTotal = array_key_exists('grand_total', $data)
                ? $data['grand_total']
                : $this->saleValidationService->calculateItemsTotal($items);
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

            $sale = new Sale;

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
                ->createItems($sale, $items, $lockedProducts);

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

            if (! $profitGuardResult['passed']) {
                throw new DomainException(
                    $profitGuardResult['message']
                );
            }

            $this->stockService
                ->deductFromSale($sale, $lockedProducts);

            $this->commissionService
                ->createFromSale($sale);

            return $sale;
        });
    }

    public function createSaleFromQuotation(Quotation $quotation): Sale
    {
        return DB::transaction(function () use ($quotation) {
            $lockedQuotation = Quotation::query()
                ->whereKey($quotation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedQuotation->status === 'converted') {
                throw new DomainException('ใบเสนอราคานี้ถูกแปลงเป็นใบขายแล้ว');
            }

            $lockedQuotation->load('items');
            $items = $lockedQuotation->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'qty' => $item->qty,
                'selling_price' => $item->selling_price,
            ])->all();

            $this->saleValidationService->assertValidItems($items);

            $sale = $this->createSale([
                'customer_id' => $lockedQuotation->customer_id,
                'sale_date' => now()->toDateString(),
                'grand_total' => $lockedQuotation->total_amount,
                'delivery_type' => 'pickup',
                'discount' => 0,
                'items' => $items,
            ]);

            $lockedQuotation->update(['status' => 'converted']);

            return $sale;
        });
    }

    public function updateSale(Sale $sale, array $data): Sale
    {
        return DB::transaction(function () use ($sale, $data) {
            $lockedSale = Sale::query()
                ->whereKey($sale->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedSale->load('items');

            $newItemsForStock = $data['items'] ?? [];
            $this->saleValidationService->assertValidItems($newItemsForStock);
            $existingItemIds = $lockedSale->items->modelKeys();

            foreach ($newItemsForStock as $item) {
                $saleItemId = $item['sale_item_id'] ?? null;
                if ($saleItemId !== null && $saleItemId !== ''
                    && ! in_array((int) $saleItemId, $existingItemIds, true)) {
                    throw new DomainException('รายการขายไม่ตรงกับใบขาย');
                }
            }

            $productIds = $lockedSale->items
                ->pluck('product_id')
                ->merge(array_column($newItemsForStock, 'product_id'))
                ->all();

            $lockedProducts = $this->stockLockService->lockProducts($productIds);
            $newItemsForStock = $this->productUnitConversionService
                ->resolveItems($newItemsForStock);

            $this->stockService->restoreFromSale(
                $lockedSale,
                $lockedProducts,
                'sale_edit',
                'คืนสต๊อกจากการแก้ไขบิล '.$lockedSale->sale_no
            );

            $this->stockLockService->assertSufficientStock(
                $lockedProducts,
                $newItemsForStock
            );

            $lockedSale->items()->delete();
            $lockedSale->unsetRelation('items');

            $this->saleItemService->createItems(
                $lockedSale,
                $newItemsForStock,
                $lockedProducts
            );

            $grandTotal = $this->saleValidationService
                ->calculateItemsTotal($newItemsForStock);

            $lockedSale->unsetRelation('items');

            $this->stockService->deductFromSale(
                $lockedSale,
                $lockedProducts,
                'sale_edit',
                'ขายออกจากการแก้ไขบิล '.$lockedSale->sale_no
            );

            $deliveryFee = $data['delivery_fee'] ?? 0;
            $discount = $data['discount'] ?? 0;
            $netTotal = $grandTotal + $deliveryFee - $discount;

            $lockedSale->update([
                'customer_id' => $data['customer_id'] ?? null,
                'sale_date' => $data['sale_date'],
                'total_amount' => $netTotal,
                'delivery_fee' => $deliveryFee,
                'discount' => $discount,
            ]);

            return $lockedSale;
        });
    }

    public function deleteSale(Sale $sale): void
    {
        DB::transaction(function () use ($sale) {
            $lockedSale = Sale::query()
                ->whereKey($sale->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedSale->load('items');

            $lockedProducts = $this->stockLockService->lockProducts(
                $lockedSale->items->pluck('product_id')->all()
            );

            $this->stockService->restoreFromSale(
                $lockedSale,
                $lockedProducts,
                'sale_delete',
                'คืนสต๊อกจากการลบบิล '.$lockedSale->sale_no
            );

            $lockedSale->items()->delete();
            $lockedSale->delete();
        });
    }
}
