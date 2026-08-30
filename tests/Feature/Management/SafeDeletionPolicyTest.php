<?php

namespace Tests\Feature\Management;

use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerDeliveryAddress;
use App\Models\DeliveryZone;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductUnit;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SafeDeletionPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_hard_delete_an_unused_delivery_zone(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $zone = DeliveryZone::query()->create(['name' => 'Unused zone', 'active' => true]);
        DeliveryZone::query()->create(['name' => 'Second zone', 'active' => true]);

        $this->actingAs($manager)
            ->deleteJson(route('delivery-zones.destroy', $zone))
            ->assertOk()
            ->assertJsonPath('action', 'deleted');

        $this->assertDatabaseMissing('delivery_zones', ['id' => $zone->id]);
    }

    public function test_referenced_delivery_zone_is_deactivated_and_last_active_zone_is_protected(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $zone = DeliveryZone::query()->create(['name' => 'Referenced zone', 'active' => true]);
        $other = DeliveryZone::query()->create(['name' => 'Other zone', 'active' => true]);
        $customer = Customer::query()->create(['name' => 'Zone customer', 'active' => true]);
        CustomerDeliveryAddress::query()->create([
            'customer_id' => $customer->id,
            'name' => 'Main address',
            'address' => 'Address',
            'delivery_zone_id' => $zone->id,
            'is_default' => true,
        ]);

        $this->actingAs($manager)
            ->deleteJson(route('delivery-zones.destroy', $zone))
            ->assertOk()
            ->assertJsonPath('action', 'deactivated');

        $this->assertDatabaseHas('delivery_zones', ['id' => $zone->id, 'active' => false]);

        $this->actingAs($manager)
            ->deleteJson(route('delivery-zones.destroy', $other))
            ->assertStatus(422)
            ->assertJsonPath('errors.zone.0', 'ต้องเหลือโซนจัดส่งที่เปิดใช้งานอย่างน้อย 1 โซน');
    }

    public function test_last_active_delivery_zone_cannot_be_disabled_from_edit(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $zone = DeliveryZone::query()->create(['name' => 'Only zone', 'active' => true]);

        $this->actingAs($manager)
            ->putJson(route('delivery-zones.update', $zone), [
                'name' => $zone->name,
                'sort_order' => 0,
                'price_markup_percent' => '0.00',
                'rounding_increment' => '0.25',
                'minimum_profit' => '0.00',
                'active' => false,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.active.0', 'ต้องเหลือโซนจัดส่งที่เปิดใช้งานอย่างน้อย 1 โซน');

        $this->assertDatabaseHas('delivery_zones', ['id' => $zone->id, 'active' => true]);
    }

    public function test_deactivated_catalog_records_can_be_restored_by_a_manager(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $category = Category::query()->create(['name' => 'Restorable category', 'active' => false]);
        $unit = Unit::query()->create([
            'code' => 'RST',
            'name' => 'Restorable unit',
            'short_name' => 'rst',
            'active' => false,
        ]);
        $zone = DeliveryZone::query()->create(['name' => 'Restorable zone', 'active' => false]);

        $this->actingAs($manager)->post(route('categories.restore', $category))->assertRedirect();
        $this->actingAs($manager)->post(route('units.restore', $unit))->assertRedirect();
        $this->actingAs($manager)->post(route('delivery-zones.restore', $zone))->assertRedirect();

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'active' => true]);
        $this->assertDatabaseHas('units', ['id' => $unit->id, 'active' => true]);
        $this->assertDatabaseHas('delivery_zones', ['id' => $zone->id, 'active' => true]);
    }

    public function test_cashier_cannot_access_catalog_delete_routes(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        $category = Category::query()->create(['name' => 'Cashier protected category', 'active' => true]);

        $this->actingAs($cashier)
            ->deleteJson(route('categories.destroy', $category))
            ->assertForbidden();
    }

    public function test_unused_product_is_deleted_but_product_with_stock_history_is_deactivated(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $category = Category::query()->create(['name' => 'Hardware', 'active' => true]);
        $unused = Product::query()->create($this->productData($category, 'Unused product'));
        $used = Product::query()->create($this->productData($category, 'Used product'));
        StockMovement::query()->create([
            'product_id' => $used->id,
            'type' => 'ADJUST',
            'qty' => 1,
            'stock_before' => 0,
            'stock_after' => 1,
            'remark' => 'opening',
        ]);

        $this->actingAs($manager)
            ->delete(route('products.destroy', $unused))
            ->assertRedirect();
        $this->assertDatabaseMissing('products', ['id' => $unused->id]);

        $this->actingAs($manager)
            ->deleteJson(route('products.destroy', $used))
            ->assertOk()
            ->assertJsonPath('action', 'deactivated');
        $this->assertDatabaseHas('products', ['id' => $used->id, 'active' => false]);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $used->id]);
    }

    public function test_nested_barcode_operations_reject_a_barcode_from_another_product(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $category = Category::query()->create(['name' => 'Tools', 'active' => true]);
        $unit = Unit::query()->create(['code' => 'PCS', 'name' => 'Pieces', 'short_name' => 'pcs', 'active' => true]);
        $first = Product::query()->create($this->productData($category, 'First product', $unit));
        $second = Product::query()->create($this->productData($category, 'Second product', $unit));
        $firstUnit = ProductUnit::query()->create([
            'product_id' => $first->id,
            'unit_id' => $unit->id,
            'conversion_rate' => 1,
            'is_base_unit' => true,
            'is_purchase_unit' => true,
            'is_sale_unit' => true,
            'active' => true,
        ]);
        $secondUnit = ProductUnit::query()->create([
            'product_id' => $second->id,
            'unit_id' => $unit->id,
            'conversion_rate' => 1,
            'is_base_unit' => true,
            'is_purchase_unit' => true,
            'is_sale_unit' => true,
            'active' => true,
        ]);
        $barcode = ProductBarcode::query()->create([
            'product_id' => $first->id,
            'product_unit_id' => $firstUnit->id,
            'barcode' => '8850000000001',
            'is_default' => true,
            'active' => true,
        ]);

        $this->actingAs($manager)
            ->delete(route('products.barcodes.destroy', [$second, $barcode]))
            ->assertNotFound();

        $this->assertDatabaseHas('product_barcodes', ['id' => $barcode->id]);
        $this->assertNotSame($firstUnit->id, $secondUnit->id);
    }

    public function test_deleting_default_product_barcode_promotes_the_next_active_barcode(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $category = Category::query()->create(['name' => 'Barcode promotion']);
        $product = Product::query()->create($this->productData($category, 'Barcode promotion product'));
        $default = ProductBarcode::query()->create([
            'product_id' => $product->id,
            'product_unit_id' => null,
            'barcode' => '8850000000101',
            'is_default' => true,
            'active' => true,
            'sort_order' => 1,
        ]);
        $next = ProductBarcode::query()->create([
            'product_id' => $product->id,
            'product_unit_id' => null,
            'barcode' => '8850000000102',
            'is_default' => false,
            'active' => true,
            'sort_order' => 2,
        ]);

        $this->actingAs($manager)
            ->delete(route('products.barcodes.destroy', [$product, $default]))
            ->assertRedirect();

        $this->assertDatabaseMissing('product_barcodes', ['id' => $default->id]);
        $this->assertDatabaseHas('product_barcodes', [
            'id' => $next->id,
            'is_default' => true,
        ]);
    }

    public function test_nested_product_unit_update_rejects_a_unit_from_another_product(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $category = Category::query()->create(['name' => 'Unit ownership']);
        $unit = Unit::query()->create(['code' => 'OWN', 'name' => 'Own', 'short_name' => 'own', 'active' => true]);
        $first = Product::query()->create($this->productData($category, 'First unit product', $unit));
        $second = Product::query()->create($this->productData($category, 'Second unit product', $unit));
        $foreignUnit = ProductUnit::query()->create([
            'product_id' => $first->id,
            'unit_id' => $unit->id,
            'conversion_rate' => 1,
            'is_base_unit' => true,
            'is_purchase_unit' => true,
            'is_sale_unit' => true,
            'active' => true,
        ]);

        $this->actingAs($manager)
            ->put(route('products.units.update', [$second, $foreignUnit]), [
                'conversion_rate' => '2.0000',
                'purchase_price' => '10.00',
                'selling_price' => '20.00',
            ])
            ->assertNotFound();
    }

    public function test_used_category_and_unit_are_deactivated_instead_of_rejected_or_deleted(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $category = Category::query()->create(['name' => 'Paint', 'active' => true]);
        $unit = Unit::query()->create(['code' => 'CAN', 'name' => 'Can', 'short_name' => 'can', 'active' => true]);
        $product = Product::query()->create($this->productData($category, 'Paint can', $unit));
        ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'conversion_rate' => 1,
            'is_base_unit' => true,
            'is_purchase_unit' => true,
            'is_sale_unit' => true,
            'active' => true,
        ]);

        $this->actingAs($manager)
            ->delete(route('categories.destroy', $category))
            ->assertRedirect();
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'active' => false]);

        $this->actingAs($manager)
            ->delete(route('units.destroy', $unit))
            ->assertRedirect();
        $this->assertDatabaseHas('units', ['id' => $unit->id, 'active' => false]);
    }

    private function productData(Category $category, string $name, ?Unit $unit = null): array
    {
        return [
            'category_id' => $category->id,
            'unit_id' => $unit?->id,
            'name' => $name,
            'product_code' => strtoupper(substr(str_replace(' ', '', $name), 0, 8)),
            'barcode' => null,
            'cost_price' => '10.00',
            'selling_price' => '15.00',
            'stock_qty' => '0.0000',
            'minimum_stock' => '0.0000',
            'active' => true,
        ];
    }
}
