<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['sales', 'hold_bills'] as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'delivery_fee_override_flag')) {
                continue;
            }

            $hasDeliveryFee = Schema::hasColumn($tableName, 'delivery_fee');
            Schema::table($tableName, function (Blueprint $table) use ($hasDeliveryFee): void {
                $column = $table->boolean('delivery_fee_override_flag')->default(false);
                if ($hasDeliveryFee) {
                    $column->after('delivery_fee');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['sales', 'hold_bills'] as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'delivery_fee_override_flag')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('delivery_fee_override_flag');
            });
        }
    }
};
