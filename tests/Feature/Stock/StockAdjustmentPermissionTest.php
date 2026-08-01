<?php

namespace Tests\Feature\Stock;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as IlluminateRoute;
use Tests\TestCase;

class StockAdjustmentPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_adjustment_routes_are_manager_protected(): void
    {
        foreach (['stock-counts.index', 'stock-counts.store'] as $name) {
            $route = app('router')->getRoutes()->getByName($name);

            $this->assertInstanceOf(IlluminateRoute::class, $route, $name);
            $this->assertContains('auth', $route->gatherMiddleware(), $name);
            $this->assertContains('role:manager', $route->gatherMiddleware(), $name);
        }
    }

    public function test_cashier_is_denied_and_manager_and_owner_can_open_stock_adjustment(): void
    {
        $this->get(route('stock-counts.index'))
            ->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create(['role' => 'cashier']))
            ->get(route('stock-counts.index'))
            ->assertForbidden();

        foreach (['manager', 'owner'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('stock-counts.index'))
                ->assertOk();
        }
    }
}
