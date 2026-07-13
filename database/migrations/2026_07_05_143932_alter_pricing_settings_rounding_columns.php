<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up(): void
{
    Schema::table('pricing_settings', function (Blueprint $table) {

        // ลบคอลัมน์เดิม
        $table->dropColumn('default_rounding_mode');

        // วิธีปัดสตางค์
        $table->string('default_satang_rounding_mode')
            ->default('ceil_satang_50');

        // วิธีปัดบาท
        $table->string('default_baht_rounding_mode')
            ->default('ceil_5');

    });
}

    /**
     * Reverse the migrations.
     */
public function down(): void
{
    Schema::table('pricing_settings', function (Blueprint $table) {

        $table->dropColumn([
            'default_satang_rounding_mode',
            'default_baht_rounding_mode',
        ]);

        $table->string('default_rounding_mode')
            ->default('5');

    });
}
};
