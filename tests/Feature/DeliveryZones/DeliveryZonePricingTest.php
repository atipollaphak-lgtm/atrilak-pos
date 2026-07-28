<?php

namespace Tests\Feature\DeliveryZones;

use App\Models\DeliveryZone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryZonePricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_view_and_update_zone_pricing_rules(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $zone = DeliveryZone::query()->create(['name' => 'ท่าเสา', 'active' => true]);

        $this->actingAs($manager)
            ->get(route('delivery-zones.index'))
            ->assertOk()
            ->assertSee('ราคาตามโซน');

        $this->actingAs($manager)
            ->put(route('delivery-zones.update', $zone), [
                'name' => 'ท่าเสา',
                'sort_order' => 0,
                'price_markup_percent' => '3.00',
                'minimum_profit' => '300.00',
                'active' => '1',
            ])
            ->assertRedirect(route('delivery-zones.index'));

        $this->assertDatabaseHas('delivery_zones', [
            'id' => $zone->id,
            'price_markup_percent' => '3.00',
            'minimum_profit' => '300.00',
        ]);
    }

    public function test_cashier_cannot_change_zone_pricing_rules(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        $zone = DeliveryZone::query()->create(['name' => 'ทดสอบ', 'active' => true]);

        $this->actingAs($cashier)
            ->put(route('delivery-zones.update', $zone), [
                'name' => $zone->name,
                'sort_order' => 0,
                'price_markup_percent' => '3.00',
                'minimum_profit' => '300.00',
                'active' => '1',
            ])
            ->assertForbidden();
    }
}
