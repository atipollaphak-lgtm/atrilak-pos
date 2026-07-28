<?php

namespace App\Services\Sales;

use App\Models\CustomerDeliveryAddress;

class DeliveryResolverService
{
    public function resolve(?int $customerDeliveryAddressId): array
    {
        if (! $customerDeliveryAddressId) {
            return [
                'delivery_address' => null,
                'delivery_zone' => null,
                'delivery_fee' => 0,
                'minimum_profit' => 0,
                'price_markup_percent' => 0,
            ];
        }

        $address = CustomerDeliveryAddress::with('deliveryZone')
            ->find($customerDeliveryAddressId);

        if (! $address) {
            return [
                'delivery_address' => null,
                'delivery_zone' => null,
                'delivery_fee' => 0,
                'minimum_profit' => 0,
                'price_markup_percent' => 0,
            ];
        }

        $zone = $address->deliveryZone;

        if (! $zone) {
            return [
                'delivery_address' => $address,
                'delivery_zone' => null,
                'delivery_fee' => 0,
                'minimum_profit' => 0,
                'price_markup_percent' => 0,
            ];
        }

        return [
            'delivery_address' => $address,
            'delivery_zone' => $zone,
            'delivery_fee' => 0,
            'minimum_profit' => (string) $zone->minimum_profit,
            'price_markup_percent' => (string) ($zone->price_markup_percent ?? 0),
        ];
    }
}
