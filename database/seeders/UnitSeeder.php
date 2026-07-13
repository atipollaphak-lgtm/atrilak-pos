<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['code' => 'PCS', 'name' => 'ชิ้น', 'short_name' => 'ชิ้น', 'sort_order' => 10],
            ['code' => 'BAG', 'name' => 'ถุง', 'short_name' => 'ถุง', 'sort_order' => 20],
            ['code' => 'PACK', 'name' => 'แพ็ค', 'short_name' => 'แพ็ค', 'sort_order' => 30],
            ['code' => 'DOZEN', 'name' => 'โหล', 'short_name' => 'โหล', 'sort_order' => 40],
            ['code' => 'BOX', 'name' => 'ลัง', 'short_name' => 'ลัง', 'sort_order' => 50],
            ['code' => 'PALLET', 'name' => 'พาเลท', 'short_name' => 'พาเลท', 'sort_order' => 60],
            ['code' => 'CUBE', 'name' => 'คิว', 'short_name' => 'คิว', 'sort_order' => 70],
            ['code' => 'BUCKET', 'name' => 'บุ้งกี๋', 'short_name' => 'บุ้ง', 'sort_order' => 80],
            ['code' => 'METER', 'name' => 'เมตร', 'short_name' => 'ม.', 'sort_order' => 90],
            ['code' => 'KG', 'name' => 'กิโลกรัม', 'short_name' => 'กก.', 'sort_order' => 100],
            ['code' => 'TON', 'name' => 'ตัน', 'short_name' => 'ตัน', 'sort_order' => 110],
        ];

        foreach ($units as $unit) {
            Unit::updateOrCreate(
                ['code' => $unit['code']],
                [
                    'name' => $unit['name'],
                    'short_name' => $unit['short_name'],
                    'active' => true,
                    'sort_order' => $unit['sort_order'],
                ]
            );
        }
    }
}
