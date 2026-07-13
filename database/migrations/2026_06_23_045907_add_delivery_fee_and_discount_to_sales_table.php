<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('delivery_fee', 12, 2)->default(0)->after('total_amount');
            $table->decimal('discount', 12, 2)->default(0)->after('delivery_fee');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['delivery_fee', 'discount']);
        });
    }
};
