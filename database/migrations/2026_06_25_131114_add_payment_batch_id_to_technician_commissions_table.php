<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technician_commissions', function (Blueprint $table) {
            if (!Schema::hasColumn('technician_commissions', 'payment_batch_id')) {
                $table->foreignId('payment_batch_id')
                    ->nullable()
                    ->after('payment_batch_no')
                    ->constrained('technician_payment_batches')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('technician_commissions', function (Blueprint $table) {
            if (Schema::hasColumn('technician_commissions', 'payment_batch_id')) {
                $table->dropConstrainedForeignId('payment_batch_id');
            }
        });
    }
};
