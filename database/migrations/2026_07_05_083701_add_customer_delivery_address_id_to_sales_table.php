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
    Schema::table('sales', function (Blueprint $table) {

        $table->foreignId('customer_delivery_address_id')
            ->nullable()
            ->after('customer_id')
            ->constrained('customer_delivery_addresses')
            ->nullOnDelete();

    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
{
    Schema::table('sales', function (Blueprint $table) {

        $table->dropForeign(['customer_delivery_address_id']);
        $table->dropColumn('customer_delivery_address_id');

    });
}
};
