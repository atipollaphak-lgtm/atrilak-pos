<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerDeliveryAddress;
use Illuminate\Support\Facades\DB;

class CustomerService
{
    public function create(array $data): Customer
    {
        return DB::transaction(function () use ($data): Customer {
            $customer = Customer::create([
                'code' => $this->nextCode(),
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'tax_number' => $data['tax_number'] ?? null,
                'branch_type' => $data['branch_type'] ?? 'สำนักงานใหญ่',
                'branch_number' => $data['branch_number'] ?? null,
                'remark' => $data['remark'] ?? null,
                'active' => true,
            ]);

            $this->savePrimaryAddress($customer, $data);

            return $customer;
        });
    }

    public function update(Customer $customer, array $data): Customer
    {
        return DB::transaction(function () use ($customer, $data): Customer {
            $customer->update([
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'tax_number' => $data['tax_number'] ?? null,
                'branch_type' => $data['branch_type'] ?? 'สำนักงานใหญ่',
                'branch_number' => $data['branch_number'] ?? null,
                'remark' => $data['remark'] ?? null,
                'active' => array_key_exists('active', $data) ? (bool) $data['active'] : $customer->active,
            ]);

            $address = isset($data['primary_address_id'])
                ? $customer->deliveryAddresses()->find($data['primary_address_id'])
                : $customer->deliveryAddresses()->where('is_default', true)->first();

            if (array_key_exists('primary_address_id', $data)
                && $data['primary_address_id'] !== null
                && $address === null) {
                abort(404);
            }

            if ($address === null) {
                $this->savePrimaryAddress($customer, $data);
            } else {
                $this->savePrimaryAddress($customer, $data, $address);
            }

            return $customer->fresh();
        });
    }

    private function savePrimaryAddress(Customer $customer, array $data, ?CustomerDeliveryAddress $address = null): CustomerDeliveryAddress
    {
        $receiverPhone = ! empty($data['use_customer_phone'])
            ? ($data['phone'] ?? $customer->phone)
            : ($data['receiver_phone'] ?? null);

        $values = [
            'name' => $data['address_name'] ?? 'หลัก',
            'receiver_name' => $data['receiver_name'] ?? null,
            'receiver_phone' => $receiverPhone,
            'address' => $data['address'] ?? null,
            'delivery_zone_id' => $data['delivery_zone_id'] ?? null,
            'is_default' => true,
        ];

        if ($address === null) {
            $customer->deliveryAddresses()->update(['is_default' => false]);

            return $customer->deliveryAddresses()->create($values);
        }

        $customer->deliveryAddresses()->where('id', '!=', $address->getKey())->update(['is_default' => false]);
        $address->update($values);

        return $address;
    }

    private function nextCode(): string
    {
        $codes = Customer::query()->lockForUpdate()->pluck('code');
        $next = $codes->reduce(function (int $carry, ?string $code): int {
            if (preg_match('/^CUS-(\d+)$/', (string) $code, $matches)) {
                return max($carry, (int) $matches[1]);
            }

            return $carry;
        }, 0) + 1;

        return 'CUS-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
