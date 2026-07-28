<?php

namespace Tests\Feature\Sales;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleV3PageTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_can_open_v3_without_changing_v2_route(): void
    {
        $response = $this->actingAs(User::factory()->create(['role' => 'cashier']))
            ->get('/sales-v3');

        $response->assertOk()
            ->assertSee('id="pos-v3"', false)
            ->assertSee('sale-v3.css', false)
            ->assertSee('sale-v3.js', false)
            ->assertSee('v3-product-search', false);

        $this->get('/sales-v2')->assertOk();
    }

    public function test_guest_cannot_open_v3(): void
    {
        $this->get('/sales-v3')->assertRedirect();
    }
}
