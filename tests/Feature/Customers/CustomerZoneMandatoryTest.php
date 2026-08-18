<?php

namespace Tests\Feature\Customers;

use App\Http\Middleware\RoleMiddleware;
use App\Models\Customer;
use App\Models\DeliveryZone;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerZoneMandatoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            Authenticate::class,
            RoleMiddleware::class,
            ValidateCsrfToken::class,
        ]);
    }

    public function test_management_customer_creation_without_zone_is_rejected(): void
    {
        $this->post(route('customers.store'), [
            'name' => 'Missing Zone Customer',
        ])->assertSessionHasErrors([
            'delivery_zone_id' => 'กรุณาเลือกโซนลูกค้า',
        ]);

        $this->assertDatabaseMissing('customers', ['name' => 'Missing Zone Customer']);
    }

    public function test_pos_customer_creation_without_zone_is_rejected_by_server(): void
    {
        $this->postJson(route('sales.v3.customers.store'), [
            'name' => 'POS Missing Zone Customer',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.delivery_zone_id.0', 'กรุณาเลือกโซนลูกค้า');

        $this->assertDatabaseMissing('customers', ['name' => 'POS Missing Zone Customer']);
    }

    public function test_new_customer_with_inactive_zone_is_rejected(): void
    {
        $zone = DeliveryZone::query()->create(['name' => 'Inactive', 'active' => false]);

        $this->postJson(route('sales.v3.customers.store'), [
            'name' => 'Inactive Zone Customer',
            'delivery_zone_id' => $zone->id,
        ])->assertUnprocessable()
            ->assertJsonPath('errors.delivery_zone_id.0', 'กรุณาเลือกโซนลูกค้า');

        $this->assertDatabaseMissing('customers', ['name' => 'Inactive Zone Customer']);
    }

    public function test_new_customer_with_active_zone_creates_primary_address_with_the_same_zone(): void
    {
        $zone = DeliveryZone::query()->create(['name' => 'North', 'active' => true]);

        $response = $this->postJson(route('sales.v3.customers.store'), [
            'name' => 'POS Zoned Customer',
            'delivery_zone_id' => $zone->id,
            'address' => '',
        ]);

        $response->assertCreated()
            ->assertJsonPath('customer.name', 'POS Zoned Customer')
            ->assertJsonPath('customer.delivery_addresses.0.delivery_zone_id', $zone->id);

        $customer = Customer::query()->where('name', 'POS Zoned Customer')->sole();
        $this->assertSame($zone->id, $customer->deliveryAddresses()->sole()->delivery_zone_id);
    }
}
