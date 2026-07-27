<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('pricing_settings', 'default_rounding_mode')) {
            Schema::table('pricing_settings', function (Blueprint $table) {
                $table->dropColumn('default_rounding_mode');
            });
        }

        if (! Schema::hasColumn('pricing_settings', 'default_satang_rounding_mode')) {
            Schema::table('pricing_settings', function (Blueprint $table) {
                $table->string('default_satang_rounding_mode')
                    ->default('ceil_satang_50');
            });
        }

        if (! Schema::hasColumn('pricing_settings', 'default_baht_rounding_mode')) {
            Schema::table('pricing_settings', function (Blueprint $table) {
                $table->string('default_baht_rounding_mode')
                    ->default('ceil_5');
            });
        }
    }

    public function down(): void
    {
        $columns = array_values(array_filter([
            'default_satang_rounding_mode',
            'default_baht_rounding_mode',
        ], fn (string $column): bool => Schema::hasColumn('pricing_settings', $column)));

        if ($columns !== []) {
            Schema::table('pricing_settings', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }

        if (! Schema::hasColumn('pricing_settings', 'default_rounding_mode')) {
            Schema::table('pricing_settings', function (Blueprint $table) {
                $table->string('default_rounding_mode')->default('5');
            });
        }
    }
};
