<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerDeliveryAddress;
use App\Models\ProductUnit;
use App\Models\Setting;
use App\Models\Technician;
use App\Models\Unit;
use Illuminate\Support\Collection;

class TransactionDocumentSnapshotService
{
    public function saleHeaderSnapshots(
        ?int $customerId,
        ?int $technicianId,
        ?int $deliveryAddressId,
        ?CustomerDeliveryAddress $deliveryAddress = null
    ): array {
        return array_merge(
            $this->storeSnapshots(),
            $this->customerSnapshots($customerId),
            $this->technicianSnapshots($technicianId),
            $this->deliveryAddressSnapshots($deliveryAddressId, $deliveryAddress)
        );
    }

    public function quotationHeaderSnapshots(?int $customerId): array
    {
        return array_merge(
            $this->storeSnapshots(),
            $this->customerSnapshots($customerId)
        );
    }

    public function customerSnapshots(?int $customerId): array
    {
        $customer = $customerId === null
            ? null
            : Customer::query()->find($customerId);

        return [
            'customer_name_snapshot' => $customer?->name,
            'customer_phone_snapshot' => $customer?->phone,
            'customer_address_snapshot' => $customer?->address,
            'customer_tax_number_snapshot' => $customer?->tax_number,
            'customer_branch_type_snapshot' => $customer?->branch_type,
            'customer_branch_number_snapshot' => $customer?->branch_number,
        ];
    }

    public function technicianSnapshots(?int $technicianId): array
    {
        $technician = $technicianId === null
            ? null
            : Technician::query()->find($technicianId);

        return [
            'technician_name_snapshot' => $technician?->name,
        ];
    }

    public function deliveryAddressSnapshots(
        ?int $deliveryAddressId,
        ?CustomerDeliveryAddress $deliveryAddress = null
    ): array {
        if ($deliveryAddressId !== null
            && (int) $deliveryAddress?->getKey() !== $deliveryAddressId) {
            $deliveryAddress = CustomerDeliveryAddress::query()
                ->find($deliveryAddressId);
        }

        return [
            'delivery_address_name_snapshot' => $deliveryAddress?->name,
            'delivery_receiver_name_snapshot' => $deliveryAddress?->receiver_name,
            'delivery_receiver_phone_snapshot' => $deliveryAddress?->receiver_phone,
            'delivery_full_address_snapshot' => $deliveryAddress?->address,
            'delivery_landmark_snapshot' => $deliveryAddress?->landmark,
        ];
    }

    public function saleItemSnapshots(array $items, Collection $products): array
    {
        $productUnitIds = collect($items)
            ->pluck('product_unit_id')
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $productUnits = $productUnitIds->isEmpty()
            ? collect()
            : ProductUnit::query()
                ->with('unit')
                ->whereIn('id', $productUnitIds->all())
                ->get()
                ->keyBy('id');

        $legacyUnitIds = collect($items)
            ->filter(fn (array $item) => ($item['product_unit_id'] ?? null) === null
                || ($item['product_unit_id'] ?? null) === '')
            ->map(function (array $item) use ($products) {
                return $products->get((int) ($item['product_id'] ?? 0))?->unit_id;
            })
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $legacyUnits = $legacyUnitIds->isEmpty()
            ? collect()
            : Unit::query()
                ->whereIn('id', $legacyUnitIds->all())
                ->get()
                ->keyBy('id');

        return collect($items)->map(function (array $item) use (
            $products,
            $productUnits,
            $legacyUnits
        ): array {
            $product = $products->get((int) ($item['product_id'] ?? 0));
            $productUnitId = $item['product_unit_id'] ?? null;

            if ($productUnitId !== null && $productUnitId !== '') {
                $unit = $productUnits->get((int) $productUnitId)?->unit;
                $legacyUnitName = null;
            } else {
                $unit = $product?->unit_id === null
                    ? null
                    : $legacyUnits->get((int) $product->unit_id);
                $legacyUnitName = $product?->unit;
            }

            return [
                'product_name_snapshot' => $product?->name,
                'product_sku_snapshot' => $product?->sku,
                'product_code_snapshot' => $product?->product_code,
                'unit_name_snapshot' => $unit?->name ?? $legacyUnitName,
                'unit_code_snapshot' => $unit?->code,
            ];
        })->all();
    }

    public function quotationItemSnapshots(Collection $products): array
    {
        return $products->mapWithKeys(fn ($product) => [
            (int) $product->getKey() => [
                'product_name_snapshot' => $product->name,
                'product_sku_snapshot' => $product->sku,
                'product_code_snapshot' => $product->product_code,
            ],
        ])->all();
    }

    private function storeSnapshots(): array
    {
        $setting = Setting::query()->first();

        return [
            'store_name_snapshot' => $setting?->store_name,
            'store_address_snapshot' => $setting?->store_address,
            'store_phone_snapshot' => $setting?->store_phone,
            'store_tax_number_snapshot' => $setting?->tax_number,
            'store_branch_type_snapshot' => $setting?->branch_type,
            'store_branch_number_snapshot' => $setting?->branch_number,
        ];
    }
}
