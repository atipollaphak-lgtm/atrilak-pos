<?php

namespace Database\Seeders;

use App\Models\DeliveryZone;
use Illuminate\Database\Seeder;

class DeliveryZoneSeeder extends Seeder
{
    public function run(): void
    {
        $zones = [
            [
                'name' => 'คลองแม่ลาย',
                'sort_order' => 1,
                'base_delivery_fee' => 50,
                'free_delivery_min_amount' => 5000,
                'active' => true,
                'remark' => null,
            ],
            [
                'name' => 'ท่าเสา',
                'sort_order' => 2,
                'base_delivery_fee' => 80,
                'free_delivery_min_amount' => 8000,
                'active' => true,
                'remark' => null,
            ],
            [
                'name' => 'ปากดง',
                'sort_order' => 3,
                'base_delivery_fee' => 100,
                'free_delivery_min_amount' => 10000,
                'active' => true,
                'remark' => null,
            ],
            [
                'name' => 'บ่อกลางดง',
                'sort_order' => 4,
                'base_delivery_fee' => 120,
                'free_delivery_min_amount' => null,
                'active' => true,
                'remark' => null,
            ],
            [
                'name' => 'ทุ่งตาพถก',
                'sort_order' => 5,
                'base_delivery_fee' => 150,
                'free_delivery_min_amount' => 15000,
                'active' => true,
                'remark' => null,
            ],
        ];

        foreach ($zones as $zone) {
            DeliveryZone::updateOrCreate(
                ['name' => $zone['name']],
                $zone
            );
        }
    }
}
