<?php

namespace Tests\Feature\Receivings;

use App\Models\Purchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceiveStockHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_has_filters_and_pagination(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        Purchase::query()->create([
            'purchase_date' => '2026-08-03',
            'total_amount' => '10.00',
            'source' => 'production',
            'status' => 'posted',
        ]);

        $this->actingAs($manager)
            ->get(route('receivings.index', ['source' => 'production']))
            ->assertOk()
            ->assertSee('ทุกแหล่งที่มา')
            ->assertSee('ผลิตเอง')
            ->assertSee('ประวัติการรับสินค้า');
    }

    public function test_supplier_filter_keeps_legacy_purchase_rows(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        Purchase::query()->create([
            'purchase_date' => '2026-08-03',
            'total_amount' => '10.00',
            'source' => null,
            'status' => null,
        ]);

        $this->actingAs($manager)
            ->get(route('receivings.index', ['source' => 'supplier']))
            ->assertOk()
            ->assertSee('ซื้อจาก Supplier');
    }
}
