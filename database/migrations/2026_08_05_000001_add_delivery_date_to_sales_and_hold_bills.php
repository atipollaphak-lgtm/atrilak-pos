<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sales', 'delivery_date')) {
            Schema::table('sales', function (Blueprint $table): void {
                $table->date('delivery_date')->nullable()->after('sale_date');
            });
        }

        if (! Schema::hasColumn('hold_bills', 'delivery_date')) {
            Schema::table('hold_bills', function (Blueprint $table): void {
                $table->date('delivery_date')->nullable()->after('sale_date');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sales', 'delivery_date')) {
            Schema::table('sales', function (Blueprint $table): void {
                $table->dropColumn('delivery_date');
            });
        }

        if (Schema::hasColumn('hold_bills', 'delivery_date')) {
            Schema::table('hold_bills', function (Blueprint $table): void {
                $table->dropColumn('delivery_date');
            });
        }
    }
};
