<?php

namespace App\Services;

use App\Models\CustomerDeliveryAddress;
use App\Models\Quotation;
use App\Models\Sale;
use App\Services\Sales\CommissionService;
use App\Services\Sales\ProductUnitConversionService;
use App\Services\Sales\ProfitGuardService;
use App\Services\Sales\SaleIdempotencyService;
use App\Services\Sales\SaleItemService;
use App\Services\Sales\SaleNumberService;
use App\Services\Sales\SaleValidationService;
use App\Services\Sales\StockService;
use Brick\Math\BigDecimal;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

    protected SaleIdempotencyService $saleIdempotencyService;

    public function __construct(
        SaleNumberService $saleNumberService,
        SaleItemService $saleItemService,
        StockService $stockService,
        CommissionService $commissionService,
        ProfitGuardService $profitGuardService,
        StockLockService $stockLockService,
        ProductUnitConversionService $productUnitConversionService,
        SaleValidationService $saleValidationService,
        SaleIdempotencyService $saleIdempotencyService
    ) {
        $this->saleNumberService = $saleNumberService;
        $this->saleItemService = $saleItemService;
        $this->stockService = $stockService;
        $this->commissionService = $commissionService;
        $this->profitGuardService = $profitGuardService;
        $this->stockLockService = $stockLockService;
        $this->productUnitConversionService = $productUnitConversionService;
        $this->saleValidationService = $saleValidationService;
        $this->saleIdempotencyService = $saleIdempotencyService;
    }

    public function createSale(array $data, ?int $quotationId = null)
    {
        $idempotencyKey = $data['idempotency_key'] ?? null;
        $payloadHash = $idempotencyKey !== null
            ? $this->saleIdempotencyService->payloadHash($data)
            : null;

        if ($idempotencyKey !== null) {
            $replay = $this->saleIdempotencyService->replay($idempotencyKey, $payloadHash);

            if ($replay !== null) {
                return $replay;
            }
        }

        try {
            return DB::transaction(function () use ($data, $idempotencyKey, $payloadHash, $quotationId) {

                $items = $data['items'] ?? [];
                $this->saleValidationService->assertValidItems($items);
                $this->saleValidationService->assertDeliveryAddressBelongsToCustomer(
                    $data['customer_delivery_address_id'] ?? null,
                    $data['customer_id'] ?? null
                );
                $lockedProducts = $this->stockLockService->lockProducts(
                    array_column($items, 'product_id')
                );

                if ($idempotencyKey !== null) {
                    $replay = $this->saleIdempotencyService->replay($idempotencyKey, $payloadHash);

                    if ($replay !== null) {
                        return $replay;
                    }
                }

                $items = $this->productUnitConversionService->resolveItems($items);
                $this->stockLockService->assertSufficientStock($lockedProducts, $items);

                $saleDate = $data['sale_date'];

                $saleNo = $this->saleNumberService
                    ->generate($saleDate);

                $grandTotal = array_key_exists('grand_total', $data)
                    ? $this->saleValidationService->money($data['grand_total'])
                    : $this->saleValidationService->calculateItemsTotal($items);
                $deliveryType = $data['delivery_type'] ?? 'delivery';
                $discount = $this->saleValidationService->money($data['discount'] ?? 0);

                $deliveryFee = '0.00';
                $minimumProfit = '0.00';
                $deliveryZoneId = null;

                if ($deliveryType === 'delivery') {
                    $address = CustomerDeliveryAddress::with('deliveryZone')
                        ->find($data['customer_delivery_address_id'] ?? null);

                    if ($address && $address->deliveryZone) {
                        $deliveryFee = $this->saleValidationService
                            ->money($address->deliveryZone->base_delivery_fee);
                        $minimumProfit = $this->saleValidationService
                            ->money($address->deliveryZone->minimum_profit);
                        $deliveryZoneId = $address->deliveryZone->id;
                    }
                } else {
                    $deliveryFee = '0.00';
                }

                $netTotal = $this->saleValidationService->calculateNetTotal(
                    $grandTotal,
                    $deliveryFee,
                    $discount
                );

                $sale = new Sale;

                $sale->sale_no = $saleNo;
                if ($quotationId !== null) {
                    $sale->quotation_id = $quotationId;
                }
                if ($idempotencyKey !== null) {
                    $sale->idempotency_key = $idempotencyKey;
                    $sale->idempotency_payload_hash = $payloadHash;
                }
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
        } catch (QueryException $exception) {
            if ($idempotencyKey !== null
                && $this->saleIdempotencyService->isIdempotencyKeyViolation($exception)) {
                $replay = $this->saleIdempotencyService->replay($idempotencyKey, $payloadHash);

                if ($replay !== null) {
                    return $replay;
                }
            }

            throw $exception;
        }
    }

    public function createSaleFromQuotation(Quotation $quotation): Sale
    {
        return DB::transaction(function () use ($quotation) {
            $lockedQuotation = Quotation::query()
                ->whereKey($quotation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $existingSale = Sale::query()
                ->where('quotation_id', $lockedQuotation->getKey())
                ->first();

            if ($lockedQuotation->status === 'converted') {
                if ($existingSale !== null) {
                    return $existingSale;
                }

                Log::warning('Converted quotation has no related sale', [
                    'quotation_id' => $lockedQuotation->getKey(),
                ]);

                throw new DomainException(
                    'ใบเสนอราคานี้ถูกแปลงแล้ว แต่ไม่มีความสัมพันธ์กับใบขายในระบบรุ่นเก่า กรุณาตรวจสอบข้อมูลก่อนดำเนินการต่อ'
                );
            }

            if ($existingSale !== null) {
                Log::warning('Quotation has a related sale but is not marked converted', [
                    'quotation_id' => $lockedQuotation->getKey(),
                    'sale_id' => $existingSale->getKey(),
                    'quotation_status' => $lockedQuotation->status,
                ]);

                throw new DomainException(
                    'สถานะใบเสนอราคาไม่สัมพันธ์กับใบขายที่มีอยู่ กรุณาตรวจสอบข้อมูลก่อนดำเนินการต่อ'
                );
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
            ], (int) $lockedQuotation->getKey());

            $lockedQuotation->update(['status' => 'converted']);

            return $sale;
        });
    }

    public function deleteQuotation(Quotation $quotation): void
    {
        DB::transaction(function () use ($quotation) {
            $lockedQuotation = Quotation::query()
                ->whereKey($quotation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (Sale::query()->where('quotation_id', $lockedQuotation->getKey())->exists()) {
                throw new DomainException(
                    'ไม่สามารถลบใบเสนอราคานี้ได้ เนื่องจากมีใบขายที่สร้างจากใบเสนอราคานี้และต้องเก็บไว้เป็นประวัติอ้างอิง'
                );
            }

            $lockedQuotation->delete();
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

            if (! $this->saleItemsHaveChanged($lockedSale->items, $newItemsForStock)) {
                $this->updateSaleHeader($lockedSale, $data);

                return $lockedSale;
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

            $this->updateSaleHeader($lockedSale, $data, $grandTotal);

            return $lockedSale;
        });
    }

    private function saleItemsHaveChanged(Collection $existingItems, array $submittedItems): bool
    {
        if ($existingItems->count() !== count($submittedItems)) {
            return true;
        }

        foreach ($existingItems->sortBy('id')->values() as $index => $existingItem) {
            $submittedItem = $submittedItems[$index] ?? null;

            if (! is_array($submittedItem)
                || (int) ($submittedItem['sale_item_id'] ?? 0) !== (int) $existingItem->id
                || (int) ($submittedItem['product_id'] ?? 0) !== (int) $existingItem->product_id
                || $this->nullableId($submittedItem['product_unit_id'] ?? null)
                    !== $this->nullableId($existingItem->product_unit_id)
                || ! $this->decimalEquals($submittedItem['qty'] ?? null, $existingItem->qty)
                || ! $this->decimalEquals(
                    $submittedItem['selling_price'] ?? null,
                    $existingItem->selling_price
                )) {
                return true;
            }
        }

        return false;
    }

    private function updateSaleHeader(
        Sale $sale,
        array $data,
        ?string $itemsTotal = null
    ): void {
        $deliveryFee = $this->saleValidationService
            ->money($data['delivery_fee'] ?? 0);
        $discount = $this->saleValidationService
            ->money($data['discount'] ?? 0);

        if ($itemsTotal === null
            && $deliveryFee === $this->saleValidationService->money($sale->delivery_fee)
            && $discount === $this->saleValidationService->money($sale->discount)) {
            $netTotal = $this->saleValidationService->money($sale->total_amount);
        } else {
            $itemsTotal ??= $this->saleValidationService
                ->calculateStoredItemsTotal($sale->items);
            $netTotal = $this->saleValidationService->calculateNetTotal(
                $itemsTotal,
                $deliveryFee,
                $discount
            );
        }

        $sale->update([
            'customer_id' => $data['customer_id'] ?? null,
            'sale_date' => $data['sale_date'],
            'total_amount' => $netTotal,
            'delivery_fee' => $deliveryFee,
            'discount' => $discount,
        ]);
    }

    private function nullableId(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function decimalEquals(mixed $left, mixed $right): bool
    {
        if ($left === null || $left === '') {
            return false;
        }

        return BigDecimal::of((string) $left)->isEqualTo(
            BigDecimal::of((string) $right)
        );
    }

    public function deleteSale(Sale $sale): void
    {
        DB::transaction(function () use ($sale) {
            $lockedSale = Sale::query()
                ->whereKey($sale->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedSale->quotation_id !== null) {
                throw new DomainException(
                    'ไม่สามารถลบใบขายนี้ได้ เนื่องจากสร้างจากใบเสนอราคาและต้องเก็บไว้เป็นประวัติอ้างอิง'
                );
            }

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
