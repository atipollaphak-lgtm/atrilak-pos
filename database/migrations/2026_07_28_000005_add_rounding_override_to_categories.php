<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONSTRAINT = 'categories_rounding_override_allowed';

    public function up(): void
    {
        if (! Schema::hasColumn('categories', 'rounding_override')) {
            Schema::table('categories', function (Blueprint $table): void {
                $table->decimal('rounding_override', 4, 2)->nullable()->after('barcode_prefix');
            });
        }

        if (DB::getDriverName() === 'pgsql') {
            $constraintExists = DB::table('pg_constraint')
                ->where('conname', self::CONSTRAINT)
                ->exists();

            if (! $constraintExists) {
                DB::statement(sprintf(
                    'ALTER TABLE categories ADD CONSTRAINT %s CHECK (rounding_override IS NULL OR rounding_override IN (0.25, 0.50, 1.00, 5.00, 10.00))',
                    self::CONSTRAINT
                ));
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(sprintf('ALTER TABLE categories DROP CONSTRAINT IF EXISTS %s', self::CONSTRAINT));
        }

        if (Schema::hasColumn('categories', 'rounding_override')) {
            Schema::table('categories', fn (Blueprint $table) => $table->dropColumn('rounding_override'));
        }
    }
};
