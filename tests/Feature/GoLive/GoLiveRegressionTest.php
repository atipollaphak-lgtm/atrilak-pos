<?php

namespace Tests\Feature\GoLive;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoLiveRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_open_all_go_live_module_entry_points(): void
    {
        $owner = User::factory()->create([
            'name' => 'TEST-GOLIVE-OWNER',
            'role' => 'owner',
        ]);

        foreach ([
            'dashboard',
            'purchases.index',
            'pricing-management.index',
            'stock-counts.index',
            'daily-payment-closings.index',
            'settings.index',
            'backups.index',
        ] as $routeName) {
            $this->actingAs($owner)
                ->get(route($routeName))
                ->assertOk();
        }

        $this->actingAs($owner)
            ->get('/sales-v3')
            ->assertOk();
    }

    public function test_business_module_entry_points_remain_manager_protected(): void
    {
        foreach ([
            'purchases.index',
            'pricing-management.index',
            'stock-counts.index',
            'daily-payment-closings.index',
        ] as $routeName) {
            $this->get(route($routeName))
                ->assertRedirect(route('login'));
        }
    }
}
