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
        Schema::table('technician_commission_rules', function (Blueprint $table) {
            if (!Schema::hasColumn('technician_commission_rules', 'name')) {
                $table->string('name')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('technician_commission_rules', function (Blueprint $table) {
            if (Schema::hasColumn('technician_commission_rules', 'name')) {
                $table->dropColumn('name');
            }
        });
    }
};
