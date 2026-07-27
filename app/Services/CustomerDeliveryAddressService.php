<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerDeliveryAddress;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CustomerDeliveryAddressService
{
    public function create(Customer $customer, array $data): CustomerDeliveryAddress
    {
        return DB::transaction(function () use ($customer, $data): CustomerDeliveryAddress {
            $isPrimary = (bool) ($data['is_default'] ?? false)
                || $customer->deliveryAddresses()->doesntExist();

            if ($isPrimary) {
                $customer->deliveryAddresses()->update(['is_default' => false]);
            }

            return $customer->deliveryAddresses()->create($this->values($customer, $data, $isPrimary));
        });
    }

    public function update(Customer $customer, CustomerDeliveryAddress $address, array $data): CustomerDeliveryAddress
    {
        return DB::transaction(function () use ($customer, $address, $data): CustomerDeliveryAddress {
            $this->assertBelongsTo($customer, $address);
            $isPrimary = (bool) ($data['is_default'] ?? false) || (bool) $address->is_default;

            if ($isPrimary) {
                $customer->deliveryAddresses()->where('id', '!=', $address->getKey())->update(['is_default' => false]);
            }

            $address->update($this->values($customer, $data, $isPrimary));

            return $address->fresh();
        });
    }

    public function setPrimary(Customer $customer, CustomerDeliveryAddress $address): void
    {
        DB::transaction(function () use ($customer, $address): void {
            $this->assertBelongsTo($customer, $address);
            $customer->deliveryAddresses()->update(['is_default' => false]);
            $address->update(['is_default' => true]);
        });
    }

    public function delete(Customer $customer, CustomerDeliveryAddress $address): void
    {
        DB::transaction(function () use ($customer, $address): void {
            $this->assertBelongsTo($customer, $address);

            if ($address->sales()->exists()) {
                throw new RuntimeException('ไม่สามารถลบที่อยู่ที่มีประวัติการขายอ้างอิงอยู่ได้');
            }

            if ($customer->deliveryAddresses()->count() <= 1) {
                throw new RuntimeException('ต้องมีที่อยู่จัดส่งอย่างน้อยหนึ่งรายการ');
            }

            $wasPrimary = (bool) $address->is_default;
            $address->delete();

            if ($wasPrimary) {
                $customer->deliveryAddresses()->oldest('id')->first()?->update(['is_default' => true]);
            }
        });
    }

    private function values(Customer $customer, array $data, bool $isPrimary): array
    {
        return [
            'name' => $data['name'] ?? 'ที่อยู่จัดส่ง',
            'receiver_name' => $data['receiver_name'] ?? null,
            'receiver_phone' => ! empty($data['use_customer_phone'])
                ? $customer->phone
                : ($data['receiver_phone'] ?? null),
            'address' => $data['address'] ?? null,
            'delivery_zone_id' => $data['delivery_zone_id'] ?? null,
            'landmark' => $data['landmark'] ?? null,
            'remark' => $data['remark'] ?? null,
            'is_default' => $isPrimary,
        ];
    }

    private function assertBelongsTo(Customer $customer, CustomerDeliveryAddress $address): void
    {
        if ((int) $address->customer_id !== (int) $customer->id) {
            abort(404);
        }
    }
}
