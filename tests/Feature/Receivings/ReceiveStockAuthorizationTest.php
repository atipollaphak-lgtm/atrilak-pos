<?php

namespace Tests\Feature\Receivings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceiveStockAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_open_receive_stock_v2_create_page(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'manager']))
            ->get(route('receivings.create'))
            ->assertOk()
            ->assertSee('รับสินค้าเข้า V2')
            ->assertSee('ไม่เปลี่ยน Selling Price');
    }

    public function test_cashier_cannot_open_receive_stock_v2(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'cashier']))
            ->get(route('receivings.create'))
            ->assertForbidden();
    }
}
