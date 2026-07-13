<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technician_payment_batches', function (Blueprint $table) {
            if (!Schema::hasColumn('technician_payment_batches', 'batch_no')) {
                $table->string('batch_no')->unique()->after('id');
            }

            if (!Schema::hasColumn('technician_payment_batches', 'payment_date')) {
                $table->date('payment_date')->nullable()->after('batch_no');
            }

            if (!Schema::hasColumn('technician_payment_batches', 'total_technicians')) {
                $table->integer('total_technicians')->default(0)->after('payment_date');
            }

            if (!Schema::hasColumn('technician_payment_batches', 'total_items')) {
                $table->integer('total_items')->default(0)->after('total_technicians');
            }

            if (!Schema::hasColumn('technician_payment_batches', 'total_amount')) {
                $table->decimal('total_amount', 15, 2)->default(0)->after('total_items');
            }

            if (!Schema::hasColumn('technician_payment_batches', 'status')) {
                $table->string('status')->default('confirmed')->after('total_amount');
            }

            if (!Schema::hasColumn('technician_payment_batches', 'remark')) {
                $table->text('remark')->nullable()->after('status');
            }

            if (!Schema::hasColumn('technician_payment_batches', 'created_by')) {
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->after('remark');
            }

            if (!Schema::hasColumn('technician_payment_batches', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete()->after('created_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('technician_payment_batches', function (Blueprint $table) {
            $table->dropColumn([
                'batch_no',
                'payment_date',
                'total_technicians',
                'total_items',
                'total_amount',
                'status',
                'remark',
                'created_by',
                'approved_by',
            ]);
        });
    }
};
