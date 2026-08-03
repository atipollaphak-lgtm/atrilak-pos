<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hold_bill_items', function (Blueprint $table): void {
            $table->decimal('original_price', 15, 2)
                ->nullable()
                ->after('selling_price');
            $table->boolean('price_override_flag')
                ->default(false)
                ->after('original_price');
        });
    }

    public function down(): void
    {
        Schema::table('hold_bill_items', function (Blueprint $table): void {
            $table->dropColumn(['original_price', 'price_override_flag']);
        });
    }
};
