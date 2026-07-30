<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hold_bill_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('product_unit_id_snapshot')
                ->nullable()
                ->after('product_unit_id');
        });
    }

    public function down(): void
    {
        Schema::table('hold_bill_items', function (Blueprint $table): void {
            $table->dropColumn('product_unit_id_snapshot');
        });
    }
};
