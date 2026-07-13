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
        Schema::table('technician_commissions', function (Blueprint $table) {
            if (!Schema::hasColumn('technician_commissions', 'rule_name')) {
                $table->string('rule_name')->nullable();
            }

            if (!Schema::hasColumn('technician_commissions', 'calculation_detail')) {
                $table->text('calculation_detail')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('technician_commissions', function (Blueprint $table) {
            if (Schema::hasColumn('technician_commissions', 'rule_name')) {
                $table->dropColumn('rule_name');
            }

            if (Schema::hasColumn('technician_commissions', 'calculation_detail')) {
                $table->dropColumn('calculation_detail');
            }
        });
    }
};
