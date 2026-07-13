<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->string('reference_type')->nullable()->after('stock_after');
            $table->unsignedBigInteger('reference_id')->nullable()->after('reference_type');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropColumn([
                'reference_type',
                'reference_id',
            ]);
        });
    }
};
