<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            if (!Schema::hasColumn('units', 'code')) {
                $table->string('code', 30)->nullable()->unique();
            }

            if (!Schema::hasColumn('units', 'short_name')) {
                $table->string('short_name', 20)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            if (Schema::hasColumn('units', 'short_name')) {
                $table->dropColumn('short_name');
            }

            if (Schema::hasColumn('units', 'code')) {
                $table->dropColumn('code');
            }
        });
    }
};
