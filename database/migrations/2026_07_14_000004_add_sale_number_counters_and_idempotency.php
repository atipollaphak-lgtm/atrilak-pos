<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $duplicate = DB::table('sales')
                ->select('sale_no')
                ->selectRaw('COUNT(*) AS occurrences')
                ->whereNotNull('sale_no')
                ->groupBy('sale_no')
                ->havingRaw('COUNT(*) > 1')
                ->orderBy('sale_no')
                ->first();

            if ($duplicate !== null) {
                throw new RuntimeException(
                    "Cannot add the sales.sale_no unique constraint: duplicate sale number {$duplicate->sale_no} exists. No sale numbers were changed."
                );
            }

            Schema::create('sale_number_counters', function (Blueprint $table) {
                $table->date('sale_date')->primary();
                $table->integer('last_number');
                $table->timestamps();
            });

            Schema::table('sales', function (Blueprint $table) {
                $table->uuid('idempotency_key')->nullable();
                $table->char('idempotency_payload_hash', 64)->nullable();
            });

            $this->initializeCounters();

            Schema::table('sales', function (Blueprint $table) {
                $table->unique('sale_no', 'sales_sale_no_unique');
                $table->unique('idempotency_key', 'sales_idempotency_key_unique');
            });

            if (DB::getDriverName() === 'pgsql') {
                DB::statement(<<<'SQL'
ALTER TABLE sales
ADD CONSTRAINT sales_idempotency_pair_check
CHECK (
    (idempotency_key IS NULL AND idempotency_payload_hash IS NULL)
    OR
    (idempotency_key IS NOT NULL AND idempotency_payload_hash IS NOT NULL)
)
SQL);
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('ALTER TABLE sales DROP CONSTRAINT IF EXISTS sales_idempotency_pair_check');
            }

            Schema::table('sales', function (Blueprint $table) {
                $table->dropUnique('sales_sale_no_unique');
                $table->dropUnique('sales_idempotency_key_unique');
                $table->dropColumn(['idempotency_key', 'idempotency_payload_hash']);
            });

            Schema::dropIfExists('sale_number_counters');
        });
    }

    private function initializeCounters(): void
    {
        $maximumByDate = [];

        DB::table('sales')
            ->whereNotNull('sale_no')
            ->orderBy('id')
            ->get(['sale_date', 'sale_no'])
            ->each(function (object $sale) use (&$maximumByDate): void {
                if (preg_match('/^SAL-(\d{8})-(\d{4,})$/D', $sale->sale_no, $matches) !== 1) {
                    return;
                }

                $saleDate = (string) $sale->sale_date;
                $suffix = (int) $matches[2];
                $maximumByDate[$saleDate] = max($maximumByDate[$saleDate] ?? 0, $suffix);
            });

        foreach ($maximumByDate as $saleDate => $maximum) {
            $now = now();

            DB::table('sale_number_counters')->insertOrIgnore([
                'sale_date' => $saleDate,
                'last_number' => $maximum,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('sale_number_counters')
                ->where('sale_date', $saleDate)
                ->where('last_number', '<', $maximum)
                ->update([
                    'last_number' => $maximum,
                    'updated_at' => $now,
                ]);
        }
    }
};
