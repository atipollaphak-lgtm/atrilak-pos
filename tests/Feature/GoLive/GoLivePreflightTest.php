<?php

namespace Tests\Feature\GoLive;

use App\Support\DatabaseEnvironmentGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GoLivePreflightTest extends TestCase
{
    use RefreshDatabase;

    public function test_testing_runtime_rejects_production_and_uses_a_safe_database(): void
    {
        $this->assertSame('testing', app()->environment());

        DatabaseEnvironmentGuard::assertSafeForTests(
            app()->environment(),
            (string) DB::connection()->getDatabaseName()
        );

        $this->assertNotSame(
            DatabaseEnvironmentGuard::PRODUCTION_DATABASE,
            DB::connection()->getDatabaseName()
        );
    }

    public function test_required_business_tables_exist_on_the_test_database(): void
    {
        foreach ([
            'products',
            'sales',
            'sale_items',
            'purchase_items',
            'stock_movements',
            'daily_payment_closings',
            'daily_payment_closing_sales',
            'settings',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing required test table: {$table}");
        }

        $this->assertTrue(Schema::hasColumns('sales', [
            'payment_method',
            'cash_amount',
            'promptpay_amount',
            'received_amount',
            'change_amount',
        ]));
    }
}
