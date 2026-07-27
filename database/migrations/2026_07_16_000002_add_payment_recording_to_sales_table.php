<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('payment_method', 20)->nullable();
            $table->decimal('cash_amount', 15, 2)->nullable();
            $table->decimal('promptpay_amount', 15, 2)->nullable();
            $table->decimal('received_amount', 15, 2)->nullable();
            $table->decimal('change_amount', 15, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn([
                'payment_method',
                'cash_amount',
                'promptpay_amount',
                'received_amount',
                'change_amount',
            ]);
        });
    }
};
