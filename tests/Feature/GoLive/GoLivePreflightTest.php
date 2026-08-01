<?php

namespace Tests\Feature\GoLive;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GoLivePreflightTest extends TestCase
{
    public function test_testing_runtime_uses_the_approved_test_database(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('atrilak_pos_final_test_20260729', DB::connection()->getDatabaseName());
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
