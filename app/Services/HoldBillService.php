<?php

namespace App\Services;

use App\Models\CustomerDeliveryAddress;
use App\Models\HoldBill;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\User;
use App\Services\Sales\SalePriceSnapshotService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class HoldBillService
{
    public function __construct(
        private readonly ?SalePriceSnapshotService $salePriceSnapshotService = null
    ) {}

    public function create(array $data, User $user): HoldBill
    {
        return DB::transaction(function () use ($data, $user): HoldBill {
            $priceSnapshotService = $this->salePriceSnapshotService
                ?? app(SalePriceSnapshotService::class);
            $zone = ($data['delivery_type'] ?? 'pickup') === 'delivery'
                ? CustomerDeliveryAddress::query()
                    ->with('deliveryZone')
                    ->find($data['customer_delivery_address_id'] ?? null)
                    ?->deliveryZone
                : null;
            $holdBill = HoldBill::query()->create([
                'hold_no' => null,
                'user_id' => $user->getKey(),
                'customer_id' => $data['customer_id'] ?? null,
                'customer_delivery_address_id' => $data['customer_delivery_address_id'] ?? null,
                'delivery_zone_id' => $data['delivery_zone_id'] ?? null,
                'delivery_zone_name_snapshot' => $data['delivery_zone_name_snapshot'] ?? null,
                'delivery_zone_markup_percent_snapshot' => $data['delivery_zone_markup_percent_snapshot'] ?? null,
                'delivery_zone_rounding_increment_snapshot' => $data['delivery_zone_rounding_increment_snapshot'] ?? null,
                'delivery_zone_minimum_profit_snapshot' => $data['delivery_zone_minimum_profit_snapshot'] ?? null,
                'sale_date' => $data['sale_date'],
                'delivery_type' => $data['delivery_type'],
                'discount' => $data['discount'] ?? 0,
                'delivery_fee' => $data['delivery_fee'] ?? 0,
                'total_amount' => $data['total_amount'],
                'notes' => $data['notes'] ?? null,
            ]);

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
                        $zone,
                        ($data['delivery_type'] ?? 'pickup') === 'pickup'
                    ),
                    (string) $item['selling_price'],
                    filter_var(
                        $item['price_was_edited'] ?? false,
                        FILTER_VALIDATE_BOOLEAN
                    )
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

            $holdBill->update([
                'hold_no' => 'HLD-'.date('Ymd', strtotime($data['sale_date'])).'-'.str_pad((string) $holdBill->getKey(), 4, '0', STR_PAD_LEFT),
            ]);

            return $holdBill->fresh(['items', 'customer', 'customerDeliveryAddress.deliveryZone']);
        });
    }

    public function list(array $filters = []): Collection
    {
        return HoldBill::query()
            ->with(['items', 'customer', 'customerDeliveryAddress.deliveryZone'])
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
            ->with(['items.product', 'items.productUnit.unit', 'customer', 'customerDeliveryAddress.deliveryZone'])
            ->findOrFail($id);
    }

    public function delete(HoldBill $holdBill): void
    {
        DB::transaction(fn () => $holdBill->fresh()->delete());
    }
}
