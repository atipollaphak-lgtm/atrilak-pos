<?php

namespace Tests\Feature\Sales;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleV3PageTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_can_open_v3_without_changing_v2_route(): void
    {
        Customer::query()->create([
            'code' => 'CUS-TEST-TAX',
            'name' => 'TEST Tax Customer',
            'tax_number' => '0100000000001',
            'active' => true,
        ]);

        $response = $this->actingAs(User::factory()->create(['role' => 'cashier']))
            ->get('/sales-v3');

        $response->assertOk()
            ->assertSee('id="pos-v3"', false)
            ->assertSee('sale-v3.css', false)
            ->assertSee('sale-v3.js', false)
            ->assertSee('v3-product-search', false)
            ->assertSee('data-document-url-template=', false)
            ->assertSee('id="v3-hold-bill"', false)
            ->assertSee('data-backdrop="static"', false)
            ->assertSee('data-keyboard="false"', false)
            ->assertSee('data-tax-number="0100000000001"', false);

        $this->get('/sales-v2')->assertOk();
    }

    public function test_guest_cannot_open_v3(): void
    {
        $this->get('/sales-v3')->assertRedirect();
    }
}
