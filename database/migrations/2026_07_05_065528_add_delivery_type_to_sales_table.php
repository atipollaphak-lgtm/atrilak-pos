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
        if (!Schema::hasColumn('sales', 'delivery_type')) {

            Schema::table('sales', function (Blueprint $table) {

                $table->string('delivery_type', 20)
                    ->default('delivery')
                    ->after('delivery_fee');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('sales', 'delivery_type')) {

            Schema::table('sales', function (Blueprint $table) {

                $table->dropColumn('delivery_type');
            });
        }
    }
};
