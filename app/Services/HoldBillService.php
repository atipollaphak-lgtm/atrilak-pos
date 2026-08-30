<?php

namespace App\Services;

use App\Models\CustomerDeliveryAddress;
use App\Models\DeliveryZone;
use App\Models\HoldBill;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\User;
use App\Services\Sales\ProductUnitConversionService;
use App\Services\Sales\SaleDecimalService;
use App\Services\Sales\SalePriceSnapshotService;
use App\Services\Sales\SaleValidationService;
use App\Services\Sales\ZonePricingService;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class HoldBillService
{
    public function __construct(
        private readonly ?SalePriceSnapshotService $salePriceSnapshotService = null,
        private readonly ?ProductUnitConversionService $productUnitConversionService = null,
        private readonly ?SaleDecimalService $saleDecimalService = null,
        private readonly ?ZonePricingService $zonePricingService = null,
        private readonly ?SaleValidationService $saleValidationService = null
    ) {}

    public function create(array $data, User $user): HoldBill
    {
        return DB::transaction(function () use ($data, $user): HoldBill {
            $priceSnapshotService = $this->salePriceSnapshotService
                ?? app(SalePriceSnapshotService::class);
            $unitConversionService = $this->productUnitConversionService
                ?? app(ProductUnitConversionService::class);
            $decimalService = $this->saleDecimalService
                ?? app(SaleDecimalService::class);
            $validationService = $this->saleValidationService
                ?? app(SaleValidationService::class);
            $zonePricingService = $this->zonePricingService
                ?? app(ZonePricingService::class);
            $pricingZoneId = $data['pricing_zone_id'] ?? null;
            $hasExplicitPricingZone = $pricingZoneId !== null && $pricingZoneId !== '';
            $deliveryType = $data['delivery_type'] ?? 'pickup';
            $pickup = $deliveryType === 'pickup';
            $manualDeliveryFee = ! $pickup
                && filter_var($data['delivery_fee_override_flag'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $deliveryZone = ($data['delivery_type'] ?? 'pickup') === 'delivery'
                ? CustomerDeliveryAddress::query()
                    ->with('deliveryZone')
                    ->find($data['customer_delivery_address_id'] ?? null)
                    ?->deliveryZone
                : null;
            if (! $pickup && $deliveryZone !== null && ! $deliveryZone->active) {
                throw new DomainException(
                    'โซนจัดส่งนี้ปิดใช้งานแล้ว กรุณาเลือกที่อยู่ที่ผูกกับโซนที่เปิดใช้งาน'
                );
            }
            $pricingZone = $hasExplicitPricingZone
                ? DeliveryZone::query()
                    ->whereKey($pricingZoneId)
                    ->where('active', true)
                    ->first()
                : (($data['delivery_type'] ?? 'pickup') === 'delivery' ? $deliveryZone : null);

            if ($hasExplicitPricingZone && $pricingZone === null) {
                throw new DomainException('โซนราคาที่เลือกไม่พร้อมใช้งาน กรุณาเลือกโซนราคาใหม่ก่อนพักบิล');
            }

            $pickup = ($data['delivery_type'] ?? 'pickup') === 'pickup';
            $holdBill = HoldBill::query()->create([
                'hold_no' => null,
                'user_id' => $user->getKey(),
                'customer_id' => $data['customer_id'] ?? null,
                'customer_delivery_address_id' => $data['customer_delivery_address_id'] ?? null,
                'delivery_zone_id' => $deliveryZone?->id,
                'delivery_zone_name_snapshot' => $deliveryZone?->name,
                'delivery_zone_markup_percent_snapshot' => $deliveryZone?->price_markup_percent,
                'delivery_zone_rounding_increment_snapshot' => $pickup ? null : $deliveryZone?->rounding_increment,
                'delivery_zone_minimum_profit_snapshot' => $deliveryZone?->minimum_profit,
                'pricing_zone_id' => $hasExplicitPricingZone ? $pricingZone?->id : null,
                'pricing_zone_name_snapshot' => $hasExplicitPricingZone ? $pricingZone?->name : null,
                'pricing_zone_markup_percent_snapshot' => $hasExplicitPricingZone
                    ? $pricingZone?->price_markup_percent
                    : null,
                'pricing_zone_rounding_increment_snapshot' => $hasExplicitPricingZone
                    ? $pricingZone?->rounding_increment
                    : null,
                'sale_date' => $data['sale_date'],
                'delivery_date' => $pickup
                    ? null
                    : ($data['delivery_date'] ?? null),
                'delivery_type' => $deliveryType,
                'discount' => $data['discount'] ?? 0,
                'delivery_fee' => 0,
                'delivery_fee_override_flag' => $manualDeliveryFee,
                // The browser total is display-only. The authoritative amount
                // is derived from the snapshotted lines, discount, and fee below.
                'total_amount' => '0.00',
                'notes' => $data['notes'] ?? null,
            ]);

            $productProfit = '0.00';
            $itemTotals = [];
            foreach ($data['items'] as $item) {
                $product = Product::query()
                    ->with(['unitRelation', 'category'])
                    ->findOrFail($item['product_id']);
                $productUnit = null;

                if (! empty($item['product_unit_id'])) {
                    $productUnit = ProductUnit::query()
                        ->with(['unit', 'priceTiers'])
                        ->whereKey($item['product_unit_id'])
                        ->where('product_id', $product->getKey())
                        ->firstOrFail();
                }

                $priceSnapshot = $priceSnapshotService->snapshot(
                    $priceSnapshotService->systemPrice(
                        $item,
                        $product,
                        $productUnit,
                        $pricingZone,
                        $pricingZone === null
                    ),
                    (string) $item['selling_price'],
                    filter_var(
                        $item['price_was_edited'] ?? false,
                        FILTER_VALIDATE_BOOLEAN
                    )
                );
                $baseQty = $productUnit !== null
                    ? $unitConversionService->calculateBaseQuantity($item['qty'], $productUnit->conversion_rate)
                    : $unitConversionService->calculateBaseQuantity($item['qty'], 1);
                $productProfit = $decimalService->addMoney(
                    $productProfit,
                    $decimalService->lineProfitFromBaseQuantity(
                        $item['qty'],
                        $priceSnapshot['selling_price'],
                        $baseQty,
                        $product->cost_price
                    )
                );
                $itemTotals[] = $decimalService->lineTotal(
                    $item['qty'],
                    $priceSnapshot['selling_price']
                );

                $holdBill->items()->create([
                    'product_id' => $product->getKey(),
                    'product_unit_id' => $productUnit?->getKey(),
                    'product_unit_id_snapshot' => $productUnit?->getKey(),
                    'qty' => $item['qty'],
                    ...$priceSnapshot,
                    'product_name_snapshot' => $product->name,
                    'product_sku_snapshot' => $product->sku,
                    'product_code_snapshot' => $product->product_code,
                    'unit_name_snapshot' => $productUnit?->unit?->name ?? $product->unit,
                    'unit_code_snapshot' => $productUnit?->unit?->code ?? $product->unitRelation?->code,
                ]);
            }

            $discount = $validationService->money($data['discount'] ?? 0);
            $productProfitAfterDiscount = $decimalService->subtractMoney(
                $productProfit,
                $discount
            );
            $deliveryFee = $pickup
                ? '0.00'
                : ($manualDeliveryFee
                    ? $validationService->nonNegativeDeliveryFee($data['delivery_fee'] ?? 0)
                    : $zonePricingService->deliveryFee(
                        $productProfitAfterDiscount,
                        $deliveryZone,
                        false
                    ));
            $itemsTotal = $decimalService->sumMoney($itemTotals);
            $totalAmount = $validationService->calculateNetTotal(
                $itemsTotal,
                $deliveryFee,
                $discount
            );

            $holdBill->update([
                'hold_no' => 'HLD-'.date('Ymd', strtotime($data['sale_date'])).'-'.str_pad((string) $holdBill->getKey(), 4, '0', STR_PAD_LEFT),
                'delivery_fee' => $deliveryFee,
                'discount' => $discount,
                'total_amount' => $totalAmount,
            ]);

            return $holdBill->fresh([
                'items',
                'customer',
                'customerDeliveryAddress.deliveryZone',
                'pricingZone',
            ]);
        });
    }

    public function list(array $filters = []): Collection
    {
        return HoldBill::query()
            ->with(['items', 'customer', 'customerDeliveryAddress.deliveryZone', 'pricingZone'])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('hold_no', 'like', '%'.$search.'%')
                        ->orWhereHas('customer', fn ($customer) => $customer
                            ->where('name', 'like', '%'.$search.'%')
                            ->orWhere('phone', 'like', '%'.$search.'%'));
                });
            })
            ->latest()
            ->get();
    }

    public function findForResume(int $id): HoldBill
    {
        return HoldBill::query()
            ->with([
                'items.product',
                'items.productUnit.unit',
                'customer',
                'customerDeliveryAddress.deliveryZone',
                'pricingZone',
            ])
            ->findOrFail($id);
    }

    public function delete(HoldBill $holdBill): void
    {
        DB::transaction(fn () => $holdBill->fresh()->delete());
    }
}
