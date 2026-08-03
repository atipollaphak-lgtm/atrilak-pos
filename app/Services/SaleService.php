<?php

namespace App\Services;

use App\Exceptions\StaleSaleRevisionException;
use App\Models\CustomerDeliveryAddress;
use App\Models\DeliveryZone;
use App\Models\HoldBill;
use App\Models\Quotation;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\Sales\CommissionService;
use App\Services\Sales\ProductUnitConversionService;
use App\Services\Sales\ProfitGuardService;
use App\Services\Sales\SaleDecimalService;
use App\Services\Sales\SaleIdempotencyService;
use App\Services\Sales\SaleItemQuantityService;
use App\Services\Sales\SaleItemService;
use App\Services\Sales\SaleNumberService;
use App\Services\Sales\SalePaymentResolver;
use App\Services\Sales\SalePriceSnapshotService;
use App\Services\Sales\SaleValidationService;
use App\Services\Sales\StockService;
use App\Services\Sales\ZonePricingService;
use App\ValueObjects\Sales\ResolvedSaleLine;
use Brick\Math\BigDecimal;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

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

    protected TransactionDocumentSnapshotService $documentSnapshotService;

    protected SaleDecimalService $saleDecimalService;

    protected SaleItemQuantityService $saleItemQuantityService;

    protected SalePaymentResolver $salePaymentResolver;

    protected ZonePricingService $zonePricingService;

    protected SalePriceSnapshotService $salePriceSnapshotService;

    public function __construct(
        SaleNumberService $saleNumberService,
        SaleItemService $saleItemService,
        StockService $stockService,
        CommissionService $commissionService,
        ProfitGuardService $profitGuardService,
        StockLockService $stockLockService,
        ProductUnitConversionService $productUnitConversionService,
        SaleValidationService $saleValidationService,
        SaleIdempotencyService $saleIdempotencyService,
        ?TransactionDocumentSnapshotService $documentSnapshotService = null,
        ?SaleDecimalService $saleDecimalService = null,
        ?SaleItemQuantityService $saleItemQuantityService = null,
        ?SalePaymentResolver $salePaymentResolver = null,
        ?ZonePricingService $zonePricingService = null,
        ?SalePriceSnapshotService $salePriceSnapshotService = null
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
        $this->documentSnapshotService = $documentSnapshotService
            ?? new TransactionDocumentSnapshotService;
        $this->saleDecimalService = $saleDecimalService ?? new SaleDecimalService;
        $this->saleItemQuantityService = $saleItemQuantityService
            ?? new SaleItemQuantityService;
        $this->salePaymentResolver = $salePaymentResolver
            ?? new SalePaymentResolver($this->saleDecimalService);
        $this->zonePricingService = $zonePricingService
            ?? new ZonePricingService($this->saleDecimalService);
        $this->salePriceSnapshotService = $salePriceSnapshotService
            ?? new SalePriceSnapshotService(
                $this->saleDecimalService,
                $this->zonePricingService
            );
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
                $holdBill = null;

                if (! empty($data['hold_bill_id'])) {
                    $holdBill = HoldBill::query()
                        ->whereKey($data['hold_bill_id'])
                        ->lockForUpdate()
                        ->first();

                    if ($holdBill === null) {
                        if ($idempotencyKey !== null) {
                            $replay = $this->saleIdempotencyService->replay($idempotencyKey, $payloadHash);

                            if ($replay !== null) {
                                return $replay;
                            }
                        }

                        throw new DomainException('รายการพักบิลนี้ถูกนำไปชำระเงินหรือถูกลบแล้ว', 409);
                    }

                    $holdBill->load([
                        'items' => fn ($query) => $query->orderBy('id'),
                    ]);
                }

                $items = $data['items'] ?? [];
                $this->saleValidationService->assertValidItems($items);
                $this->saleValidationService->assertDeliveryAddressBelongsToCustomer(
                    $data['customer_delivery_address_id'] ?? null,
                    $data['customer_id'] ?? null
                );
                $lockedProducts = $this->stockLockService->lockProducts(
                    array_column($items, 'product_id')
                );
                $lockedProducts->load('productUnits');
                $lockedProducts->load('category');
                if (Schema::hasTable('product_price_tiers')) {
                    $lockedProducts->load('productUnits.priceTiers');
                }

                if ($idempotencyKey !== null) {
                    $replay = $this->saleIdempotencyService->replay($idempotencyKey, $payloadHash);

                    if ($replay !== null) {
                        return $replay;
                    }
                }

                $resolvedLines = $this->productUnitConversionService
                    ->resolveLines($items, $lockedProducts);

                if ($holdBill !== null) {
                    $data['hold_price_snapshots'] = $holdBill->items
                        ->map(fn ($item): array => [
                            'selling_price' => $item->selling_price,
                            'original_price' => $item->original_price,
                            'price_override_flag' => (bool) $item->price_override_flag,
                        ])
                        ->values()
                        ->all();
                }

                $sale = $this->persistResolvedSale(
                    $data,
                    $resolvedLines,
                    $lockedProducts,
                    $quotationId,
                    $idempotencyKey,
                    $payloadHash,
                    true
                );

                $holdBill?->delete();

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

            $quotationItems = $lockedQuotation->items()
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $lockedProducts = $this->stockLockService->lockProducts(
                $quotationItems->pluck('product_id')->all()
            );
            $resolvedLines = $this->productUnitConversionService
                ->resolveStoredQuotationLines($quotationItems, $lockedProducts);
            $items = collect($resolvedLines)
                ->map(fn ($line): array => $line->toArray())
                ->all();

            $this->saleValidationService->assertValidItems($items);
            $this->assertQuotationFinancialConsistency(
                $lockedQuotation,
                $quotationItems
            );

            $sale = $this->persistResolvedSale([
                'customer_id' => $lockedQuotation->customer_id,
                'sale_date' => now()->toDateString(),
                'grand_total' => $lockedQuotation->total_amount,
                'delivery_type' => 'pickup',
                'discount' => 0,
            ], $resolvedLines, $lockedProducts, (int) $lockedQuotation->getKey(), null, null, false);

            $lockedQuotation->update(['status' => 'converted']);

            return $sale;
        });
    }

    /**
     * @param  list<ResolvedSaleLine>  $resolvedLines
     */
    private function persistResolvedSale(
        array $data,
        array $resolvedLines,
        Collection $lockedProducts,
        ?int $quotationId = null,
        ?string $idempotencyKey = null,
        ?string $payloadHash = null,
        bool $requiresPayment = true
    ): Sale {
        $requiredBaseQtyByProduct = $this->productUnitConversionService
            ->aggregateBaseQuantityByProduct($resolvedLines);
        $items = collect($resolvedLines)
            ->map(fn ($line): array => $line->toArray())
            ->all();
        [$zone, $address] = $this->resolveZoneContext($data);
        $pickup = ($data['delivery_type'] ?? 'delivery') === 'pickup';
        $effectiveRoundingIncrement = null;
        $items = collect($items)->map(function (array $item, int $index) use (&$effectiveRoundingIncrement, $data, $lockedProducts, $zone, $pickup): array {
            $holdSnapshot = $data['hold_price_snapshots'][$index] ?? null;
            $priceChangedSinceHold = filter_var(
                $item['price_changed_since_hold'] ?? false,
                FILTER_VALIDATE_BOOLEAN
            );

            if ($holdSnapshot !== null && ! $priceChangedSinceHold) {
                return [
                    ...$item,
                    ...$holdSnapshot,
                ];
            }

            if (! array_key_exists('price_was_edited', $item)) {
                if ($pickup) {
                    return $item;
                }

                $product = $lockedProducts->get((int) $item['product_id']);
                $product?->loadMissing('category');
                $unit = $product?->productUnits?->firstWhere('id', $item['product_unit_id'] ?? null);
                $pricing = $this->zonePricingService->priceLine($item, $product, $unit, $zone, $pickup);
                $effectiveRoundingIncrement ??= $pricing['rounding_increment'];

                return [
                    ...$item,
                    'selling_price' => $pricing['zone_unit_price'],
                ];
            }

            $product = $lockedProducts->get((int) $item['product_id']);
            $product?->loadMissing('category');
            $unit = $product?->productUnits?->firstWhere('id', $item['product_unit_id'] ?? null);
            $pricing = $this->zonePricingService->priceLine($item, $product, $unit, $zone, $pickup);
            $effectiveRoundingIncrement ??= $pricing['rounding_increment'];
            $priceWasEdited = filter_var(
                $item['price_was_edited'],
                FILTER_VALIDATE_BOOLEAN
            );
            $priceSnapshot = $this->salePriceSnapshotService->snapshot(
                $pricing['zone_unit_price'],
                (string) $item['selling_price'],
                $priceWasEdited
            );

            return [
                ...$item,
                ...$priceSnapshot,
            ];
        })->all();
        $this->stockLockService->assertSufficientBaseQuantities(
            $lockedProducts,
            $requiredBaseQtyByProduct
        );

        $saleDate = $data['sale_date'];
        $grandTotal = $this->saleValidationService->calculateItemsTotal($items);
        $deliveryType = $data['delivery_type'] ?? 'delivery';
        $discount = $this->saleValidationService->money($data['discount'] ?? 0);
        $deliveryFee = '0.00';
        $minimumProfit = '0.00';
        $deliveryZoneId = null;
        $deliveryAddressId = $data['customer_delivery_address_id'] ?? null;
        if ($deliveryType === 'delivery' && $zone !== null) {
            $minimumProfit = $this->saleValidationService->money($zone->minimum_profit);
            $deliveryZoneId = $zone->id;
        }

        $rawProductProfit = $this->saleDecimalService->sumMoney(
            collect($items)->map(fn (array $item): string => $this->saleDecimalService->lineProfitFromBaseQuantity(
                $item['qty'],
                $item['selling_price'],
                $item['base_qty'],
                $lockedProducts->get((int) $item['product_id'])->cost_price ?? 0
            ))
        );
        $productProfitAfterDiscount = $this->saleDecimalService->subtractMoney(
            $rawProductProfit,
            $discount
        );
        $deliveryFee = $this->zonePricingService->deliveryFee(
            $productProfitAfterDiscount,
            $zone,
            $pickup
        );

        $netTotal = $this->saleValidationService->calculateNetTotal(
            $grandTotal,
            $deliveryFee,
            $discount
        );
        $payment = $requiresPayment
            ? $this->salePaymentResolver->resolve(
                $netTotal,
                $data['payment_method'] ?? null,
                $data['cash_amount'] ?? null,
                $data['promptpay_amount'] ?? null,
                $data['received_amount'] ?? null
            )
            : null;
        $saleNo = $this->saleNumberService->generate($saleDate);
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
        $sale->customer_delivery_address_id = $deliveryAddressId;
        $sale->technician_id = $data['technician_id'] ?? null;
        $sale->sale_date = $saleDate;
        $sale->total_amount = $netTotal;
        $sale->delivery_fee = $deliveryFee;
        $sale->delivery_type = $deliveryType;
        $sale->discount = $discount;
        $sale->delivery_zone_id = $deliveryZoneId;
        $sale->delivery_zone_name_snapshot = $zone?->name;
        $sale->delivery_zone_markup_percent_snapshot = $zone?->price_markup_percent;
        $sale->delivery_zone_rounding_increment_snapshot = $pickup
            ? null
            : ($effectiveRoundingIncrement ?? $zone?->rounding_increment);
        $sale->delivery_zone_minimum_profit_snapshot = $zone?->minimum_profit;
        $sale->notes = $data['notes'] ?? null;

        if ($payment !== null) {
            $sale->fill($payment);
        }

        $sale->fill($this->documentSnapshotService->saleHeaderSnapshots(
            $this->nullableId($data['customer_id'] ?? null),
            $this->nullableId($data['technician_id'] ?? null),
            $this->nullableId($deliveryAddressId),
            $address
        ));
        $sale->save();

        $itemSnapshots = $this->documentSnapshotService
            ->saleItemSnapshots($items, $lockedProducts, true);
        $this->saleItemService
            ->createItemsForNewSale($sale, $items, $lockedProducts, $itemSnapshots);
        $productProfit = $this->saleDecimalService->sumMoney(
            $sale->items()->pluck('profit')
        );
        $profitGuardResult = $this->profitGuardService->check([
            'delivery_type' => $deliveryType,
            'delivery_fee' => $deliveryFee,
            'delivery_zone_id' => $deliveryZoneId,
            'minimum_profit' => $minimumProfit,
        ], $productProfit);

        if (! $profitGuardResult['passed']) {
            throw new DomainException($profitGuardResult['message']);
        }

        $this->stockService->deductFromSale($sale, $lockedProducts);
        $this->commissionService->createFromSale($sale);

        return $sale;
    }

    /** @return array{0: ?DeliveryZone, 1: ?CustomerDeliveryAddress} */
    private function resolveZoneContext(array $data): array
    {
        if (($data['delivery_type'] ?? 'delivery') === 'pickup') {
            return [null, null];
        }

        $addressId = $data['customer_delivery_address_id'] ?? null;
        if ($addressId === null || $addressId === '') {
            throw new DomainException('การจัดส่งต้องเลือกที่อยู่จัดส่งและโซนที่ใช้งานอยู่');
        }

        $address = CustomerDeliveryAddress::with('deliveryZone')->find($addressId);
        $zone = $address?->deliveryZone;
        if ($address === null || $zone === null) {
            throw new DomainException('ที่อยู่จัดส่งนี้ยังไม่ได้ผูกโซน กรุณาเลือกที่อยู่หรือแก้ข้อมูลโซนก่อนบันทึกบิล');
        }
        if (! $zone->active) {
            throw new DomainException('โซนจัดส่งนี้ปิดใช้งานแล้ว กรุณาเลือกโซนที่เปิดใช้งาน');
        }

        return [$zone, $address];
    }

    private function assertQuotationFinancialConsistency(
        Quotation $quotation,
        Collection $quotationItems
    ): void {
        $canonicalTotals = [];

        foreach ($quotationItems->values() as $index => $item) {
            $canonicalTotal = $this->saleDecimalService->lineTotal(
                $item->qty,
                $item->selling_price
            );
            $storedTotal = $this->saleDecimalService->money($item->total);

            if (! BigDecimal::of($canonicalTotal)->isEqualTo($storedTotal)) {
                $itemNumber = $index + 1;

                throw new DomainException("ยอดรวมของรายการใบเสนอราคาที่ {$itemNumber} ไม่ถูกต้อง");
            }

            $canonicalTotals[] = $canonicalTotal;
        }

        $canonicalHeader = $this->saleDecimalService->sumMoney($canonicalTotals);
        $storedHeader = $this->saleDecimalService->money($quotation->total_amount);

        if (! BigDecimal::of($canonicalHeader)->isEqualTo($storedHeader)) {
            throw new DomainException('ยอดรวมใบเสนอราคาไม่ตรงกับยอดรวมรายการสินค้า');
        }
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

    public function updateSale(
        Sale $sale,
        array $data,
        int $expectedRevision
    ): Sale {
        return DB::transaction(function () use ($sale, $data, $expectedRevision) {
            $lockedSale = Sale::query()
                ->whereKey($sale->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $lockedSale->revision !== $expectedRevision) {
                throw new StaleSaleRevisionException;
            }

            if ($lockedSale->isVoided()) {
                throw new DomainException('ใบขายนี้ถูกยกเลิกแล้ว ไม่สามารถแก้ไขได้');
            }

            $lockedItems = SaleItem::query()
                ->where('sale_id', $lockedSale->getKey())
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $lockedSale->setRelation('items', $lockedItems);
            $lockedCommissions = $this->commissionService
                ->lockForSale($lockedSale);
            $this->saleItemQuantityService
                ->assertAuthoritativeQuantities($lockedItems);

            $newItemsForStock = $data['items'] ?? [];
            $this->saleValidationService->assertValidItems($newItemsForStock);
            $existingItemIds = $lockedItems->modelKeys();
            $submittedExistingIds = [];

            foreach ($newItemsForStock as $item) {
                $saleItemId = $item['sale_item_id'] ?? null;
                if ($saleItemId !== null && $saleItemId !== ''
                    && ! in_array((int) $saleItemId, $existingItemIds, true)) {
                    throw new DomainException('รายการขายไม่ตรงกับใบขาย');
                }

                if ($saleItemId !== null && $saleItemId !== '') {
                    $saleItemId = (int) $saleItemId;
                    if (in_array($saleItemId, $submittedExistingIds, true)) {
                        throw new DomainException('รายการขายเดิมถูกส่งมาซ้ำ กรุณาตรวจสอบรายการสินค้า');
                    }
                    $submittedExistingIds[] = $saleItemId;
                }
            }

            $itemsChanged = $this->saleItemsHaveChanged($lockedItems, $newItemsForStock);

            if (! $itemsChanged) {
                $itemsTotal = $this->saleValidationService
                    ->calculateStoredItemsTotal($lockedItems);
                $data['delivery_fee'] = $this->authoritativeDeliveryFee(
                    $lockedSale,
                    $data,
                    $this->saleDecimalService->subtractMoney(
                        $this->saleDecimalService->sumMoney($lockedItems->pluck('profit')),
                        $data['discount'] ?? $lockedSale->discount
                    )
                );
                $finalNetTotal = $this->finalNetTotal($lockedSale, $data, $itemsTotal);
                $commissionAffected = $this->headerAffectsCommission(
                    $lockedSale,
                    $data,
                    $finalNetTotal
                );
                $this->commissionService->assertCanChange(
                    $lockedCommissions,
                    $commissionAffected
                );

                if ($this->headerNeedsProfitGuard($lockedSale, $data)) {
                    $this->assertUpdateProfitGuard(
                        $lockedSale,
                        $data,
                        $this->saleDecimalService->sumMoney(
                            $lockedItems->pluck('profit')
                        )
                    );
                }

                $payment = $this->resolveUpdatedPayment(
                    $lockedSale,
                    $data,
                    $finalNetTotal
                );
                $this->updateSaleHeader($lockedSale, $data, null, $payment);

                if ($commissionAffected) {
                    $this->commissionService->refreshPendingForSale(
                        $lockedSale->fresh('items'),
                        $lockedCommissions
                    );
                }

                $this->advanceRevision($lockedSale);

                return $lockedSale;
            }

            $productIds = $lockedItems
                ->pluck('product_id')
                ->merge(array_column($newItemsForStock, 'product_id'))
                ->all();

            $lockedProducts = $this->stockLockService->lockProducts($productIds);
            $lockedProducts->load('productUnits');
            $lockedProducts->load('category');
            if (Schema::hasTable('product_price_tiers')) {
                $lockedProducts->load('productUnits.priceTiers');
            }
            [$priceZone] = $this->resolveZoneContext([
                'delivery_type' => $data['delivery_type'] ?? $lockedSale->delivery_type,
                'customer_delivery_address_id' => $data['customer_delivery_address_id']
                    ?? $lockedSale->customer_delivery_address_id,
            ]);
            $resolvedItems = collect(
                $this->productUnitConversionService->resolveLines(
                    $newItemsForStock,
                    $lockedProducts
                )
            )->map(fn ($line): array => $line->toArray())->all();
            $updatePlan = $this->buildUpdatePlan(
                $lockedItems,
                $resolvedItems,
                $lockedProducts,
                $lockedSale,
                $data,
                $priceZone
            );
            $plannedAttributes = collect($updatePlan['lines'])->pluck('attributes');
            $grandTotal = $this->saleDecimalService->sumMoney(
                $plannedAttributes->pluck('total')
            );
            $productProfit = $this->saleDecimalService->sumMoney(
                $plannedAttributes->pluck('profit')
            );
            $data['delivery_fee'] = $this->authoritativeDeliveryFee(
                $lockedSale,
                $data,
                $this->saleDecimalService->subtractMoney(
                    $productProfit,
                    $data['discount'] ?? $lockedSale->discount
                )
            );
            $finalNetTotal = $this->finalNetTotal($lockedSale, $data, $grandTotal);
            $commissionAffected = $updatePlan['commission_affected']
                || $this->headerAffectsCommission(
                    $lockedSale,
                    $data,
                    $finalNetTotal
                );

            $this->commissionService->assertCanChange(
                $lockedCommissions,
                $commissionAffected
            );
            $this->assertUpdateProfitGuard($lockedSale, $data, $productProfit);
            $payment = $this->resolveUpdatedPayment(
                $lockedSale,
                $data,
                $finalNetTotal
            );

            $this->stockService->restoreFromSale(
                $lockedSale,
                $lockedProducts,
                'sale_edit',
                'คืนสต๊อกจากการแก้ไขบิล '.$lockedSale->sale_no
            );

            $this->stockLockService->assertSufficientStock(
                $lockedProducts,
                $plannedAttributes->all()
            );

            $this->stockService->deductLines(
                $lockedSale,
                $plannedAttributes,
                $lockedProducts,
                'sale_edit',
                'ขายออกจากการแก้ไขบิล '.$lockedSale->sale_no
            );

            $this->persistUpdatePlan($lockedSale, $updatePlan);
            $this->updateSaleHeader($lockedSale, $data, $grandTotal, $payment);

            if ($commissionAffected) {
                $lockedSale->unsetRelation('items');
                $this->commissionService->refreshPendingForSale(
                    $lockedSale->fresh('items'),
                    $lockedCommissions
                );
            }

            $this->advanceRevision($lockedSale);

            return $lockedSale;
        });
    }

    private function advanceRevision(Sale $sale): void
    {
        $sale->revision = (int) $sale->revision + 1;
        $sale->save();
    }

    private function saleItemsHaveChanged(Collection $existingItems, array $submittedItems): bool
    {
        if ($existingItems->count() !== count($submittedItems)) {
            return true;
        }

        foreach ($existingItems->sortBy('id')->values() as $index => $existingItem) {
            $submittedItem = $submittedItems[$index] ?? null;

            if (! is_array($submittedItem)
                || ! $this->saleItemMatches($existingItem, $submittedItem)
                || in_array($submittedItem['price_action'] ?? null, ['override', 'system'], true)) {
                return true;
            }
        }

        return false;
    }

    private function updateSaleHeader(
        Sale $sale,
        array $data,
        ?string $itemsTotal = null,
        ?array $payment = null
    ): void {
        $deliveryFee = $this->saleValidationService
            ->money($data['delivery_fee'] ?? $sale->delivery_fee);
        $discount = $this->saleValidationService
            ->money($data['discount'] ?? $sale->discount);

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

        $updates = [
            'customer_id' => array_key_exists('customer_id', $data)
                ? $data['customer_id']
                : $sale->customer_id,
            'sale_date' => $data['sale_date'] ?? $sale->sale_date,
            'total_amount' => $netTotal,
            'delivery_fee' => $deliveryFee,
            'discount' => $discount,
        ];

        $deliveryType = $data['delivery_type'] ?? $sale->delivery_type;
        $deliveryAddressId = $data['customer_delivery_address_id'] ?? $sale->customer_delivery_address_id;
        [$zone] = $this->resolveZoneContext([
            'delivery_type' => $deliveryType,
            'customer_delivery_address_id' => $deliveryAddressId,
        ]);
        $updates['delivery_type'] = $deliveryType;
        $updates['delivery_zone_id'] = $zone?->id;
        $updates['delivery_zone_name_snapshot'] = $sale->delivery_zone_name_snapshot ?? $zone?->name;
        $updates['delivery_zone_markup_percent_snapshot'] = $sale->delivery_zone_markup_percent_snapshot
            ?? $zone?->price_markup_percent;
        $updates['delivery_zone_rounding_increment_snapshot'] = $deliveryType === 'pickup'
            ? null
            : ($sale->delivery_zone_rounding_increment_snapshot ?? $zone?->rounding_increment);
        $updates['delivery_zone_minimum_profit_snapshot'] = $sale->delivery_zone_minimum_profit_snapshot
            ?? $zone?->minimum_profit;

        if ($payment !== null) {
            $updates = array_merge($updates, $payment);
        }

        if ($this->nullableId($data['customer_id'] ?? null)
            !== $this->nullableId($sale->customer_id)) {
            $updates = array_merge(
                $updates,
                $this->documentSnapshotService->customerSnapshots(
                    $this->nullableId($data['customer_id'] ?? null)
                )
            );
        }

        if (array_key_exists('technician_id', $data)
            && $this->nullableId($data['technician_id'])
                !== $this->nullableId($sale->technician_id)) {
            $updates['technician_id'] = $data['technician_id'];
            $updates = array_merge(
                $updates,
                $this->documentSnapshotService->technicianSnapshots(
                    $this->nullableId($data['technician_id'])
                )
            );
        }

        if (array_key_exists('customer_delivery_address_id', $data)
            && $this->nullableId($data['customer_delivery_address_id'])
                !== $this->nullableId($sale->customer_delivery_address_id)) {
            $updates['customer_delivery_address_id'] = $data['customer_delivery_address_id'];
            $updates = array_merge(
                $updates,
                $this->documentSnapshotService->deliveryAddressSnapshots(
                    $this->nullableId($data['customer_delivery_address_id'])
                )
            );
        }

        $sale->update($updates);
    }

    private function resolveUpdatedPayment(
        Sale $sale,
        array $data,
        string $netTotal
    ): ?array {
        $paymentFields = ['payment_method', 'cash_amount', 'promptpay_amount', 'received_amount'];
        $hasPaymentInput = collect($paymentFields)
            ->contains(fn (string $field): bool => array_key_exists($field, $data));

        if (! $hasPaymentInput) {
            return null;
        }

        return $this->salePaymentResolver->resolve(
            $netTotal,
            $data['payment_method'] ?? null,
            $data['cash_amount'] ?? null,
            $data['promptpay_amount'] ?? null,
            $data['received_amount'] ?? null
        );
    }

    private function buildUpdatePlan(
        Collection $existingItems,
        array $submittedItems,
        Collection $lockedProducts,
        Sale $sale,
        array $data,
        ?DeliveryZone $priceZone
    ): array {
        $freshSnapshots = $this->documentSnapshotService
            ->saleItemSnapshots($submittedItems, $lockedProducts, true);
        $existingItemsById = $existingItems->keyBy('id');
        $retainedIds = [];
        $commissionAffected = false;
        $snapshotColumns = [
            'product_name_snapshot',
            'product_sku_snapshot',
            'product_code_snapshot',
            'unit_name_snapshot',
            'unit_code_snapshot',
        ];

        $pickup = ($data['delivery_type'] ?? $sale->delivery_type) === 'pickup';

        $lines = collect($submittedItems)->map(function (
            array $submittedItem,
            int $index
        ) use (
            $existingItemsById,
            $freshSnapshots,
            $snapshotColumns,
            $lockedProducts,
            $priceZone,
            $pickup,
            &$retainedIds,
            &$commissionAffected
        ): array {
            $existingItem = $existingItemsById->get(
                (int) ($submittedItem['sale_item_id'] ?? 0)
            );
            $sameProduct = $existingItem !== null
                && (int) $existingItem->product_id === (int) $submittedItem['product_id'];
            $sameIdentity = $sameProduct
                && $this->nullableId($existingItem->product_unit_id)
                    === $this->nullableId($submittedItem['product_unit_id'] ?? null);
            $sameQuantityIdentity = $sameIdentity
                && $this->decimalEquals($existingItem->qty, $submittedItem['qty']);

            if ($existingItem !== null) {
                $retainedIds[] = (int) $existingItem->id;
            }

            if ($sameQuantityIdentity) {
                $submittedItem['conversion_rate_used'] = $existingItem->conversion_rate_used;
                $submittedItem['base_qty'] = $this->saleItemQuantityService
                    ->authoritativeBaseQuantity($existingItem);
            }

            $snapshots = $sameIdentity
                ? collect($snapshotColumns)->mapWithKeys(fn (string $column) => [
                    $column => $existingItem->getAttribute($column),
                ])->all()
                : $freshSnapshots[$index];
            $costPrice = $sameProduct
                ? $existingItem->cost_price
                : $lockedProducts->get((int) $submittedItem['product_id'])?->cost_price;

            if ($costPrice === null) {
                throw new DomainException('ไม่พบสินค้า');
            }

            $priceAction = $this->priceActionForUpdate(
                $submittedItem,
                $existingItem,
                $sameIdentity
            );
            $product = $lockedProducts->get((int) $submittedItem['product_id']);
            $productUnit = $product?->productUnits
                ?->firstWhere('id', $submittedItem['product_unit_id'] ?? null);
            $resolvedItem = $submittedItem;
            $priceSnapshot = [];

            if (in_array($priceAction, ['preserve', 'legacy'], true) && $sameIdentity) {
                $resolvedItem['selling_price'] = $existingItem->selling_price;
                $priceSnapshot = [
                    'original_price' => $existingItem->original_price,
                    'price_override_flag' => (bool) $existingItem->price_override_flag,
                ];
            } elseif ($priceAction === 'legacy') {
                $priceSnapshot = [
                    'original_price' => null,
                    'price_override_flag' => false,
                ];
            } else {
                $systemPrice = $this->salePriceSnapshotService->systemPrice(
                    $resolvedItem,
                    $product,
                    $productUnit,
                    $priceZone,
                    $pickup
                );
                $priceSnapshot = $this->salePriceSnapshotService->snapshot(
                    $systemPrice,
                    (string) $submittedItem['selling_price'],
                    $priceAction === 'override'
                );
                $resolvedItem = array_merge($resolvedItem, $priceSnapshot);
            }

            $exactMatch = in_array($priceAction, ['preserve', 'legacy'], true)
                && $existingItem !== null
                && $this->saleItemMatches($existingItem, $submittedItem);
            $attributes = $exactMatch
                ? array_merge($existingItem->only([
                    'product_id',
                    'product_unit_id',
                    'conversion_rate_used',
                    'base_qty',
                    'qty',
                    'selling_price',
                    'original_price',
                    'price_override_flag',
                    'cost_price',
                    'total',
                    'profit',
                ]), $snapshots)
                : $this->saleItemService->attributesForResolvedLine(
                    $resolvedItem,
                    $costPrice,
                    array_merge($snapshots, $priceSnapshot)
                );

            if ($existingItem === null
                || ! $sameProduct
                || ! $this->decimalEquals($existingItem->qty, $submittedItem['qty'])
                || ! $this->decimalEquals(
                    $existingItem->selling_price,
                    $attributes['selling_price']
                )) {
                $commissionAffected = true;
            }

            return [
                'existing_item' => $existingItem,
                'attributes' => $attributes,
                'persist' => ! $exactMatch,
            ];
        })->all();

        $removedItems = $existingItems
            ->reject(fn (SaleItem $item): bool => in_array(
                (int) $item->id,
                $retainedIds,
                true
            ))
            ->values();

        return [
            'lines' => $lines,
            'removed_items' => $removedItems,
            'commission_affected' => $commissionAffected || $removedItems->isNotEmpty(),
        ];
    }

    private function priceActionForUpdate(
        array $submittedItem,
        ?SaleItem $existingItem,
        bool $sameIdentity
    ): string {
        $priceAction = $submittedItem['price_action'] ?? null;

        if ($priceAction === null || $priceAction === '') {
            if (! $sameIdentity || $existingItem === null) {
                return 'legacy';
            }

            return ! $this->decimalEquals(
                $existingItem->selling_price,
                $submittedItem['selling_price'] ?? null
            )
                ? 'override'
                : 'preserve';
        }

        if (! in_array($priceAction, ['preserve', 'override', 'system'], true)) {
            throw new DomainException('invalid price action');
        }

        return $priceAction;
    }

    private function persistUpdatePlan(Sale $sale, array $plan): void
    {
        foreach ($plan['lines'] as $line) {
            if (! $line['persist']) {
                continue;
            }

            $item = $line['existing_item'];
            if ($item === null) {
                $sale->items()->create($line['attributes']);
            } else {
                $item->fill($line['attributes']);
                $item->save();
            }
        }

        $plan['removed_items']->each(fn (SaleItem $item) => $item->delete());
        $sale->unsetRelation('items');
    }

    private function finalNetTotal(Sale $sale, array $data, string $itemsTotal): string
    {
        return $this->saleValidationService->calculateNetTotal(
            $itemsTotal,
            $data['delivery_fee'] ?? $sale->delivery_fee,
            $data['discount'] ?? $sale->discount
        );
    }

    private function headerAffectsCommission(
        Sale $sale,
        array $data,
        string $finalNetTotal
    ): bool {
        return (string) ($data['sale_date'] ?? $sale->sale_date) !== (string) $sale->sale_date
            || $this->nullableId($data['technician_id'] ?? $sale->technician_id)
                !== $this->nullableId($sale->technician_id)
            || ! $this->decimalEquals($finalNetTotal, $sale->total_amount);
    }

    private function headerNeedsProfitGuard(Sale $sale, array $data): bool
    {
        foreach (['delivery_fee', 'discount', 'delivery_type', 'customer_delivery_address_id'] as $field) {
            if (array_key_exists($field, $data)
                && (string) $data[$field] !== (string) $sale->getAttribute($field)) {
                return true;
            }
        }

        return false;
    }

    private function assertUpdateProfitGuard(
        Sale $sale,
        array $data,
        string $productProfit
    ): void {
        $deliveryType = $data['delivery_type'] ?? $sale->delivery_type;
        $deliveryAddressId = $data['customer_delivery_address_id']
            ?? $sale->customer_delivery_address_id;
        $deliveryFee = $this->saleValidationService->money(
            $data['delivery_fee'] ?? $sale->delivery_fee
        );
        $minimumProfit = '0.00';
        $deliveryZoneId = null;

        if ($deliveryType === 'delivery' && $deliveryAddressId !== null) {
            $address = CustomerDeliveryAddress::with('deliveryZone')
                ->find($deliveryAddressId);
            if ($address?->deliveryZone !== null) {
                $minimumProfit = $this->saleValidationService
                    ->money($address->deliveryZone->minimum_profit);
                $deliveryZoneId = $address->deliveryZone->id;
            }
        }

        $result = $this->profitGuardService->check([
            'delivery_type' => $deliveryType,
            'delivery_fee' => $deliveryFee,
            'delivery_zone_id' => $deliveryZoneId,
            'minimum_profit' => $minimumProfit,
        ], $productProfit);

        if (! $result['passed']) {
            throw new DomainException($result['message']);
        }
    }

    private function authoritativeDeliveryFee(Sale $sale, array $data, mixed $profitAfterDiscount): string
    {
        $deliveryType = $data['delivery_type'] ?? $sale->delivery_type;
        $addressId = $data['customer_delivery_address_id'] ?? $sale->customer_delivery_address_id;
        [$zone] = $this->resolveZoneContext([
            'delivery_type' => $deliveryType,
            'customer_delivery_address_id' => $addressId,
        ]);

        return $this->zonePricingService->deliveryFee(
            $profitAfterDiscount,
            $zone,
            $deliveryType === 'pickup'
        );
    }

    private function saleItemMatches($existingItem, array $submittedItem): bool
    {
        return (int) ($submittedItem['sale_item_id'] ?? 0) === (int) $existingItem->id
            && (int) ($submittedItem['product_id'] ?? 0) === (int) $existingItem->product_id
            && $this->nullableId($submittedItem['product_unit_id'] ?? null)
                === $this->nullableId($existingItem->product_unit_id)
            && $this->decimalEquals($submittedItem['qty'] ?? null, $existingItem->qty)
            && $this->decimalEquals(
                $submittedItem['selling_price'] ?? null,
                $existingItem->selling_price
            );
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

            $lockedItems = SaleItem::query()
                ->where('sale_id', $lockedSale->getKey())
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $lockedSale->setRelation('items', $lockedItems);
            $lockedCommissions = $this->commissionService
                ->lockForSale($lockedSale);
            $this->commissionService->assertCanChange($lockedCommissions, true);
            $this->saleItemQuantityService
                ->assertAuthoritativeQuantities($lockedItems);

            $lockedProducts = $this->stockLockService->lockProducts(
                $lockedItems->pluck('product_id')->all()
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

    public function voidSale(Sale $sale, User $actor, string $reason): Sale
    {
        $voidReason = trim($reason);

        if ($voidReason === '') {
            throw new DomainException('กรุณาระบุเหตุผลการยกเลิกใบขาย');
        }

        return DB::transaction(function () use ($sale, $actor, $voidReason): Sale {
            $lockedSale = Sale::query()
                ->whereKey($sale->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedSale->isVoided()) {
                throw new DomainException('ใบขายนี้ถูกยกเลิกแล้ว');
            }

            if (! $lockedSale->isActive()) {
                throw new DomainException('ใบขายนี้ไม่สามารถยกเลิกได้');
            }

            $lockedItems = SaleItem::query()
                ->where('sale_id', $lockedSale->getKey())
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $lockedSale->setRelation('items', $lockedItems);

            $lockedCommissions = $this->commissionService->lockForSale($lockedSale);
            $this->commissionService->assertCanChange($lockedCommissions, true);
            $this->saleItemQuantityService
                ->assertAuthoritativeQuantities($lockedItems);

            $lockedProducts = $this->stockLockService->lockProducts(
                $lockedItems->pluck('product_id')->all()
            );

            $this->stockService->restoreFromSale(
                $lockedSale,
                $lockedProducts,
                'sale_void',
                'คืนสต็อกจากการยกเลิกใบขาย '.$lockedSale->sale_no
            );

            $this->commissionService->voidPendingForSale($lockedCommissions);

            $lockedSale->forceFill([
                'status' => Sale::STATUS_VOIDED,
                'voided_at' => now(),
                'voided_by' => $actor->getKey(),
                'void_reason' => $voidReason,
            ])->save();

            return $lockedSale;
        });
    }
}
