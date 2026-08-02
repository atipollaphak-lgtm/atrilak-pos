<?php

namespace Tests\Feature\Sales;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleV2PageTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_can_open_v2_without_development_label(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'cashier']))
            ->get(route('sales.v2'))
            ->assertOk()
            ->assertSee('POS V2')
            ->assertDontSee('กำลังพัฒนา');
    }

    public function test_guest_cannot_open_v2(): void
    {
        $this->get(route('sales.v2'))
            ->assertRedirect(route('login'));
    }
}
