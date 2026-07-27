<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('store_name_snapshot')->nullable();
            $table->text('store_address_snapshot')->nullable();
            $table->string('store_phone_snapshot')->nullable();
            $table->string('store_tax_number_snapshot')->nullable();
            $table->string('store_branch_type_snapshot')->nullable();
            $table->string('store_branch_number_snapshot')->nullable();
            $table->string('customer_name_snapshot')->nullable();
            $table->string('customer_phone_snapshot')->nullable();
            $table->text('customer_address_snapshot')->nullable();
            $table->string('customer_tax_number_snapshot')->nullable();
            $table->string('customer_branch_type_snapshot')->nullable();
            $table->string('customer_branch_number_snapshot')->nullable();
            $table->string('technician_name_snapshot')->nullable();
            $table->string('delivery_address_name_snapshot')->nullable();
            $table->string('delivery_receiver_name_snapshot')->nullable();
            $table->string('delivery_receiver_phone_snapshot', 30)->nullable();
            $table->text('delivery_full_address_snapshot')->nullable();
            $table->text('delivery_landmark_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn([
                'store_name_snapshot',
                'store_address_snapshot',
                'store_phone_snapshot',
                'store_tax_number_snapshot',
                'store_branch_type_snapshot',
                'store_branch_number_snapshot',
                'customer_name_snapshot',
                'customer_phone_snapshot',
                'customer_address_snapshot',
                'customer_tax_number_snapshot',
                'customer_branch_type_snapshot',
                'customer_branch_number_snapshot',
                'technician_name_snapshot',
                'delivery_address_name_snapshot',
                'delivery_receiver_name_snapshot',
                'delivery_receiver_phone_snapshot',
                'delivery_full_address_snapshot',
                'delivery_landmark_snapshot',
            ]);
        });
    }
};
