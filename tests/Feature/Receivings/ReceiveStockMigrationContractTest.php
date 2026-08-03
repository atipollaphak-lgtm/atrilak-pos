<?php

namespace Tests\Feature\Receivings;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReceiveStockMigrationContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_tables_expose_receive_stock_v2_audit_fields(): void
    {
        foreach ([
            'supplier_id',
            'source',
            'supplier_document_number',
            'status',
            'created_by',
            'idempotency_key',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('purchases', $column), "Missing purchases.{$column}");
        }

        foreach ([
            'product_unit_id',
            'conversion_rate_used',
            'base_qty',
            'unit_name_snapshot',
            'unit_code_snapshot',
            'average_cost_before',
            'average_cost_after',
            'stock_before',
            'stock_after',
            'stock_movement_id',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('purchase_items', $column), "Missing purchase_items.{$column}");
        }
    }
}
