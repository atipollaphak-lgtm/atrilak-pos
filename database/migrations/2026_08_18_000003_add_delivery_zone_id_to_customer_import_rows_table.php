<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('customer_import_rows', 'delivery_zone_id')) {
            Schema::table('customer_import_rows', function (Blueprint $table): void {
                $table->foreignId('delivery_zone_id')
                    ->nullable()
                    ->after('customer_id')
                    ->constrained('delivery_zones')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('customer_import_rows', 'delivery_zone_id')) {
            Schema::table('customer_import_rows', function (Blueprint $table): void {
                $table->dropForeign(['delivery_zone_id']);
                $table->dropColumn('delivery_zone_id');
            });
        }
    }
};
