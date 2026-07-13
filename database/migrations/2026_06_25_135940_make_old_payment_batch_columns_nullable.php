<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technician_payment_batches', function (Blueprint $table) {
            if (Schema::hasColumn('technician_payment_batches', 'technician_id')) {
                $table->unsignedBigInteger('technician_id')->nullable()->change();
            }

            if (Schema::hasColumn('technician_payment_batches', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('technician_payment_batches', function (Blueprint $table) {
            if (Schema::hasColumn('technician_payment_batches', 'technician_id')) {
                $table->unsignedBigInteger('technician_id')->nullable(false)->change();
            }

            if (Schema::hasColumn('technician_payment_batches', 'paid_at')) {
                $table->timestamp('paid_at')->nullable(false)->change();
            }
        });
    }
};
