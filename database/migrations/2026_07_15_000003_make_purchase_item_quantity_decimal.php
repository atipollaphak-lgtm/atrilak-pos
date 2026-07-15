<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_items', function (Blueprint $table): void {
            $table->decimal('qty', 19, 4)->change();
        });
    }

    public function down(): void
    {
        $wrapped = DB::connection()->getQueryGrammar()->wrap('qty');
        $expression = DB::connection()->getDriverName() === 'pgsql'
            ? "{$wrapped} <> trunc({$wrapped}, 2)"
            : "{$wrapped} <> round({$wrapped}, 2)";

        if (DB::table('purchase_items')->whereRaw($expression)->exists()) {
            throw new RuntimeException(
                'Cannot reduce purchase_items.qty: fractional values beyond 2 decimal places would be lost.'
            );
        }

        Schema::table('purchase_items', function (Blueprint $table): void {
            $table->decimal('qty', 12, 2)->change();
        });
    }
};
