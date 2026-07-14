<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->decimal('conversion_rate_used', 15, 4)
                ->nullable()
                ->after('product_unit_id');
            $table->decimal('base_qty', 19, 4)
                ->nullable()
                ->after('conversion_rate_used');
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn(['conversion_rate_used', 'base_qty']);
        });
    }
};
