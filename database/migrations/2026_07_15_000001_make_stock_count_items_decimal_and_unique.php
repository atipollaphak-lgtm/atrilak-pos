<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const UNIQUE_INDEX = 'stock_count_items_stock_count_product_unique';

    public function up(): void
    {
        if ($this->hasDuplicateProducts()) {
            throw new RuntimeException(
                'Cannot add the Stock Count Item unique constraint: duplicate products exist within a Stock Count.'
            );
        }

        Schema::table('stock_count_items', function (Blueprint $table) {
            $table->decimal('system_qty', 19, 4)->default(0)->change();
            $table->decimal('actual_qty', 19, 4)->default(0)->change();
            $table->decimal('difference', 19, 4)->default(0)->change();
            $table->unique(['stock_count_id', 'product_id'], self::UNIQUE_INDEX);
        });
    }

    public function down(): void
    {
        $this->assertColumnsHaveNoFractionalValues([
            'system_qty',
            'actual_qty',
            'difference',
        ]);

        Schema::table('stock_count_items', function (Blueprint $table) {
            $table->dropUnique(self::UNIQUE_INDEX);
            $table->integer('system_qty')->default(0)->change();
            $table->integer('actual_qty')->default(0)->change();
            $table->integer('difference')->default(0)->change();
        });
    }

    private function hasDuplicateProducts(): bool
    {
        return DB::table('stock_count_items')
            ->select(['stock_count_id', 'product_id'])
            ->groupBy('stock_count_id', 'product_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();
    }

    private function assertColumnsHaveNoFractionalValues(array $columns): void
    {
        foreach ($columns as $column) {
            $wrapped = DB::connection()->getQueryGrammar()->wrap($column);
            $expression = DB::connection()->getDriverName() === 'pgsql'
                ? "{$wrapped} <> trunc({$wrapped}, 0)"
                : "{$wrapped} <> round({$wrapped}, 0)";

            if (DB::table('stock_count_items')->whereRaw($expression)->exists()) {
                throw new RuntimeException(
                    "Cannot reduce stock_count_items.{$column}: fractional values would be lost."
                );
            }
        }
    }
};
