<?php

namespace Tests\Feature\DeliveryZones;

use App\Models\DeliveryZone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DeliveryZonePricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_zone_rounding_increment_defaults_to_quarter_baht(): void
    {
        $zone = DeliveryZone::query()->create(['name' => 'Default rounding', 'active' => true]);

        $this->assertSame('0.25', $zone->fresh()->rounding_increment);
    }

    public function test_zone_forms_render_rounding_options_and_live_preview(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $zone = DeliveryZone::query()->create(['name' => 'Preview zone', 'active' => true]);

        $this->actingAs($manager)
            ->get(route('delivery-zones.create'))
            ->assertOk()
            ->assertSee('zone-pricing-preview', false)
            ->assertSee('ปัดขึ้นทีละ 10.00 บาท');

        $this->actingAs($manager)
            ->get(route('delivery-zones.edit', $zone))
            ->assertOk()
            ->assertSee('ราคาสำหรับทดลองคำนวณ');
    }

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
                'rounding_increment' => '0.50',
                'minimum_profit' => '300.00',
                'active' => '1',
            ])
            ->assertRedirect(route('delivery-zones.index'));

        $this->assertDatabaseHas('delivery_zones', [
            'id' => $zone->id,
            'price_markup_percent' => '3.00',
            'rounding_increment' => '0.50',
            'minimum_profit' => '300.00',
        ]);
    }

    #[DataProvider('invalidRoundingIncrementProvider')]
    public function test_zone_rounding_increment_must_be_one_of_the_supported_values(string $increment): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $zone = DeliveryZone::query()->create(['name' => 'Validation zone', 'active' => true]);

        $this->actingAs($manager)
            ->from(route('delivery-zones.edit', $zone))
            ->put(route('delivery-zones.update', $zone), [
                'name' => $zone->name,
                'sort_order' => 0,
                'price_markup_percent' => '3.00',
                'rounding_increment' => $increment,
                'minimum_profit' => '300.00',
                'active' => '1',
            ])
            ->assertSessionHasErrors('rounding_increment');
    }

    public static function invalidRoundingIncrementProvider(): array
    {
        return [
            'too small' => ['0.10'],
            'unsupported quarter' => ['0.30'],
            'negative' => ['-0.25'],
        ];
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
