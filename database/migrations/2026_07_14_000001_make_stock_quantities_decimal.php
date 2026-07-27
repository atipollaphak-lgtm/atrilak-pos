<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('stock_qty', 19, 4)->default(0)->change();
            $table->decimal('minimum_stock', 19, 4)->default(0)->change();
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->decimal('qty', 19, 4)->change();
            $table->decimal('stock_before', 19, 4)->change();
            $table->decimal('stock_after', 19, 4)->change();
        });
    }

    public function down(): void
    {
        $this->assertColumnsFitScale('products', ['stock_qty', 'minimum_stock'], 0);
        $this->assertColumnsFitScale('stock_movements', ['qty', 'stock_before', 'stock_after'], 2);

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->decimal('qty', 12, 2)->change();
            $table->decimal('stock_before', 12, 2)->change();
            $table->decimal('stock_after', 12, 2)->change();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->integer('stock_qty')->default(0)->change();
            $table->integer('minimum_stock')->default(0)->change();
        });
    }

    private function assertColumnsFitScale(string $table, array $columns, int $scale): void
    {
        foreach ($columns as $column) {
            $wrapped = DB::connection()->getQueryGrammar()->wrap($column);
            $expression = DB::connection()->getDriverName() === 'pgsql'
                ? "{$wrapped} <> trunc({$wrapped}, {$scale})"
                : "{$wrapped} <> round({$wrapped}, {$scale})";

            if (DB::table($table)->whereRaw($expression)->exists()) {
                throw new RuntimeException(
                    "Cannot reduce {$table}.{$column}: fractional values would be lost."
                );
            }
        }
    }
};
