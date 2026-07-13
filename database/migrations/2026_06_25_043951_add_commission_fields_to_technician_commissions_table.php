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
            if (!Schema::hasColumn('technician_commissions', 'commission_date')) {
                $table->date('commission_date')->nullable();
            }

            if (!Schema::hasColumn('technician_commissions', 'sale_total')) {
                $table->decimal('sale_total', 15, 2)->default(0);
            }

            if (!Schema::hasColumn('technician_commissions', 'commission_rate')) {
                $table->decimal('commission_rate', 5, 2)->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('technician_commissions', function (Blueprint $table) {
            if (Schema::hasColumn('technician_commissions', 'commission_date')) {
                $table->dropColumn('commission_date');
            }

            if (Schema::hasColumn('technician_commissions', 'sale_total')) {
                $table->dropColumn('sale_total');
            }

            if (Schema::hasColumn('technician_commissions', 'commission_rate')) {
                $table->dropColumn('commission_rate');
            }
        });
    }
};
