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
        if (Schema::hasColumn('delivery_zones', 'sort_order')) {
            return;
        }

        Schema::table('delivery_zones', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')
                ->default(0)
                ->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('delivery_zones', 'sort_order')) {
            return;
        }

        Schema::table('delivery_zones', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
