<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->string('code_prefix', 20)->nullable()->unique();
            $table->string('barcode_prefix', 3)->nullable()->unique();
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->unique('product_code');
            $table->unique('barcode');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropUnique(['product_code']);
            $table->dropUnique(['barcode']);
        });

        Schema::table('categories', function (Blueprint $table): void {
            $table->dropUnique(['code_prefix']);
            $table->dropUnique(['barcode_prefix']);
            $table->dropColumn(['code_prefix', 'barcode_prefix']);
        });
    }
};
