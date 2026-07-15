<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->string('product_name_snapshot')->nullable();
            $table->string('product_sku_snapshot')->nullable();
            $table->string('product_code_snapshot')->nullable();
            $table->string('unit_name_snapshot')->nullable();
            $table->string('unit_code_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn([
                'product_name_snapshot',
                'product_sku_snapshot',
                'product_code_snapshot',
                'unit_name_snapshot',
                'unit_code_snapshot',
            ]);
        });
    }
};
