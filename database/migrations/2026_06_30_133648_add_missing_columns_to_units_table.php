<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            if (!Schema::hasColumn('units', 'active')) {
                $table->boolean('active')->default(true);
            }

            if (!Schema::hasColumn('units', 'sort_order')) {
                $table->integer('sort_order')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            if (Schema::hasColumn('units', 'sort_order')) {
                $table->dropColumn('sort_order');
            }

            if (Schema::hasColumn('units', 'active')) {
                $table->dropColumn('active');
            }
        });
    }
};
