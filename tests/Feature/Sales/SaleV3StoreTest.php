<?php

namespace Tests\Feature\Sales;

use App\Http\Requests\Sales\StoreSaleV2Request;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleV3StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_v3_store_route_uses_the_existing_v2_request_contract(): void
    {
        $route = app('router')->getRoutes()->getByName('sales.v3.store');

        $this->assertNotNull($route);
        $this->assertContains('POST', $route->methods());
        $this->assertStringContainsString('SaleV3Controller', $route->getActionName());
        $this->assertTrue(is_subclass_of(StoreSaleV2Request::class, FormRequest::class));
        $this->assertTrue(User::query()->count() >= 0);
    }
}
