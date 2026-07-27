<?php

namespace Tests\Feature\Pricing;

use App\Http\Controllers\PricingManagementController;
use App\Http\Controllers\ProductController;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ScheduledPricingRemovalTest extends TestCase
{
    public function test_scheduler_has_no_events(): void
    {
        $this->assertCount(0, app(Schedule::class)->events());
    }

    public function test_scheduled_pricing_command_is_not_discovered(): void
    {
        $this->assertArrayNotHasKey('pricing:apply-scheduled', Artisan::all());
    }

    public function test_manual_pricing_routes_keep_their_existing_role_boundaries(): void
    {
        $pricingManagementRoute = Route::getRoutes()->getByName('pricing-management.index');
        $productUpdateRoute = Route::getRoutes()->getByName('products.update');

        $this->assertNotNull($pricingManagementRoute);
        $this->assertSame(
            PricingManagementController::class.'@index',
            $pricingManagementRoute->getActionName()
        );
        $this->assertContains('role:owner', $pricingManagementRoute->gatherMiddleware());

        $this->assertNotNull($productUpdateRoute);
        $this->assertSame(
            ProductController::class.'@update',
            $productUpdateRoute->getActionName()
        );
        $this->assertContains('role:manager', $productUpdateRoute->gatherMiddleware());
    }
}
