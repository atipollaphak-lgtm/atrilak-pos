<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_items', function (Blueprint $table): void {
            $table->foreignId('product_unit_id')
                ->nullable()
                ->after('product_id')
                ->constrained('product_units')
                ->nullOnDelete();
            $table->decimal('conversion_rate_used', 15, 4)
                ->nullable()
                ->after('product_unit_id');
            $table->decimal('base_qty', 19, 4)
                ->nullable()
                ->after('conversion_rate_used');
            $table->decimal('qty', 15, 2)->change();
            $table->string('unit_name_snapshot')->nullable();
            $table->string('unit_code_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        $wrapped = DB::connection()->getQueryGrammar()->wrap('qty');
        $expression = DB::connection()->getDriverName() === 'pgsql'
            ? "{$wrapped} <> trunc({$wrapped})"
            : "{$wrapped} <> CAST({$wrapped} AS INTEGER)";

        if (DB::table('quotation_items')->whereRaw($expression)->exists()) {
            throw new RuntimeException(
                'Cannot reduce quotation_items.qty to integer: fractional values would be lost.'
            );
        }

        Schema::table('quotation_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('product_unit_id');
            $table->dropColumn([
                'conversion_rate_used',
                'base_qty',
                'unit_name_snapshot',
                'unit_code_snapshot',
            ]);
            $table->integer('qty')->change();
        });
    }
};
