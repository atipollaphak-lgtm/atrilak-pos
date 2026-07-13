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
            if (!Schema::hasColumn('technician_commission_rules', 'rule_type')) {
                $table->string('rule_type')->default('percent');
            }

            if (!Schema::hasColumn('technician_commission_rules', 'rule_value')) {
                $table->decimal('rule_value', 15, 2)->default(0);
            }

            if (!Schema::hasColumn('technician_commission_rules', 'active')) {
                $table->boolean('active')->default(true);
            }

            if (!Schema::hasColumn('technician_commission_rules', 'remark')) {
                $table->text('remark')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('technician_commission_rules', function (Blueprint $table) {
            if (Schema::hasColumn('technician_commission_rules', 'rule_type')) {
                $table->dropColumn('rule_type');
            }

            if (Schema::hasColumn('technician_commission_rules', 'rule_value')) {
                $table->dropColumn('rule_value');
            }

            if (Schema::hasColumn('technician_commission_rules', 'active')) {
                $table->dropColumn('active');
            }

            if (Schema::hasColumn('technician_commission_rules', 'remark')) {
                $table->dropColumn('remark');
            }
        });
    }
};
