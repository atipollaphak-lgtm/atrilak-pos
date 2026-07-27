<?php

namespace Tests\Feature\Customers;

use App\Http\Middleware\RoleMiddleware;
use App\Models\Customer;
use App\Models\CustomerDeliveryAddress;
use App\Models\DeliveryZone;
use App\Models\Sale;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerModuleTest extends TestCase
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

    public function test_customer_create_generates_code_and_primary_address_in_one_flow(): void
    {
        $zone = DeliveryZone::create(['name' => 'North', 'active' => true]);

        $response = $this->post(route('customers.store'), [
            'name' => 'Builder One',
            'phone' => '0800000001',
            'delivery_zone_id' => $zone->id,
            'address' => '99/1 site road',
            'use_customer_phone' => '1',
            'code' => 'EVIL-CODE',
        ]);

        $customer = Customer::query()->firstOrFail();
        $address = $customer->deliveryAddresses()->firstOrFail();

        $response->assertRedirect(route('customers.show', $customer));
        $this->assertSame('CUS-0001', $customer->code);
        $this->assertTrue($address->is_default);
        $this->assertSame('0800000001', $address->receiver_phone);
    }

    public function test_customer_update_updates_primary_address_without_creating_another(): void
    {
        $zone = DeliveryZone::create(['name' => 'North', 'active' => true]);
        $customer = Customer::create(['code' => 'CUS-0001', 'name' => 'Builder One', 'phone' => '0800000001']);
        $address = CustomerDeliveryAddress::create([
            'customer_id' => $customer->id,
            'name' => 'หลัก',
            'address' => 'Old address',
            'delivery_zone_id' => $zone->id,
            'is_default' => true,
            'receiver_phone' => '0811111111',
        ]);

        $response = $this->put(route('customers.update', $customer), [
            'name' => 'Builder Updated',
            'phone' => '0800000002',
            'primary_address_id' => $address->id,
            'delivery_zone_id' => $zone->id,
            'address' => 'New address',
            'receiver_phone' => '0899999999',
            'use_customer_phone' => '0',
        ]);

        $response->assertRedirect(route('customers.show', $customer));
        $this->assertSame(1, $customer->deliveryAddresses()->count());
        $this->assertSame('Builder Updated', $customer->fresh()->name);
        $this->assertSame('New address', $address->fresh()->address);
        $this->assertSame('0899999999', $address->fresh()->receiver_phone);
    }

    public function test_customer_list_searches_all_addresses_and_has_one_show_action(): void
    {
        $zone = DeliveryZone::create(['name' => 'North', 'sort_order' => 1, 'active' => true]);
        $customer = Customer::create(['code' => 'CUS-0001', 'name' => 'Builder One', 'phone' => '0800000001']);
        CustomerDeliveryAddress::create([
            'customer_id' => $customer->id,
            'name' => 'Site',
            'address' => 'Non-primary site road',
            'delivery_zone_id' => $zone->id,
            'is_default' => false,
            'receiver_phone' => '0899999999',
        ]);

        $this->get(route('customers.index', ['search' => 'Non-primary']))
            ->assertOk()
            ->assertSee('Builder One')
            ->assertSee('ดูรายละเอียด')
            ->assertDontSee('แก้ไขข้อมูลลูกค้า');
    }

    public function test_profile_displays_addresses_and_sales_history(): void
    {
        $customer = Customer::create(['code' => 'CUS-0001', 'name' => 'Builder One']);
        CustomerDeliveryAddress::create([
            'customer_id' => $customer->id,
            'name' => 'หลัก',
            'address' => 'Site road',
            'is_default' => true,
        ]);
        Sale::create([
            'customer_id' => $customer->id,
            'sale_no' => 'SAL-TEST-0001',
            'sale_date' => '2026-07-01',
            'total_amount' => 1234,
            'status' => Sale::STATUS_ACTIVE,
        ]);

        $this->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('Site road')
            ->assertSee('SAL-TEST-0001');
    }

    public function test_setting_primary_address_clears_the_previous_primary(): void
    {
        $customer = Customer::create(['code' => 'CUS-0001', 'name' => 'Builder One']);
        $first = CustomerDeliveryAddress::create(['customer_id' => $customer->id, 'name' => 'First', 'address' => 'A', 'is_default' => true]);
        $second = CustomerDeliveryAddress::create(['customer_id' => $customer->id, 'name' => 'Second', 'address' => 'B', 'is_default' => false]);

        $this->post(route('customers.delivery-addresses.set-primary', [$customer, $second]))
            ->assertRedirect(route('customers.show', $customer));

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
    }
}
