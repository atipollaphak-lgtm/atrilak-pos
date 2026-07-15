<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MAX_COUNTER = 2147483647;

    public function up(): void
    {
        DB::transaction(function (): void {
            $duplicate = DB::table('stock_counts')
                ->select('count_no')
                ->selectRaw('COUNT(*) AS occurrences')
                ->whereNotNull('count_no')
                ->groupBy('count_no')
                ->havingRaw('COUNT(*) > 1')
                ->orderBy('count_no')
                ->first();

            if ($duplicate !== null) {
                throw new RuntimeException(
                    "Cannot add the stock_counts.count_no unique constraint: duplicate Stock Count number {$duplicate->count_no} exists. No Stock Count numbers were changed."
                );
            }

            Schema::create('stock_count_number_counters', function (Blueprint $table): void {
                $table->date('count_date')->primary();
                $table->integer('last_number');
                $table->timestamps();
            });

            $this->initializeCounters();

            Schema::table('stock_counts', function (Blueprint $table): void {
                $table->unique('count_no', 'stock_counts_count_no_unique');
            });
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            Schema::table('stock_counts', function (Blueprint $table): void {
                $table->dropUnique('stock_counts_count_no_unique');
            });

            Schema::dropIfExists('stock_count_number_counters');
        });
    }

    private function initializeCounters(): void
    {
        $maximumByPrefixDate = [];

        DB::table('stock_counts')
            ->whereNotNull('count_no')
            ->orderBy('id')
            ->get(['id', 'count_no'])
            ->each(function (object $stockCount) use (&$maximumByPrefixDate): void {
                if (preg_match('/^SC-(\d{8})-(\d{4,})$/D', $stockCount->count_no, $matches) !== 1) {
                    return;
                }

                $prefixDate = $this->validPrefixDate($matches[1]);

                if ($prefixDate === null) {
                    return;
                }

                $suffix = $this->integerSuffix($matches[2], (int) $stockCount->id);
                $maximumByPrefixDate[$prefixDate] = max(
                    $maximumByPrefixDate[$prefixDate] ?? 0,
                    $suffix
                );
            });

        foreach ($maximumByPrefixDate as $countDate => $maximum) {
            $now = now();

            DB::table('stock_count_number_counters')->insertOrIgnore([
                'count_date' => $countDate,
                'last_number' => $maximum,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('stock_count_number_counters')
                ->where('count_date', $countDate)
                ->where('last_number', '<', $maximum)
                ->update([
                    'last_number' => $maximum,
                    'updated_at' => $now,
                ]);
        }
    }

    private function validPrefixDate(string $prefix): ?string
    {
        $date = DateTimeImmutable::createFromFormat('!Ymd', $prefix);

        if ($date === false || $date->format('Ymd') !== $prefix) {
            return null;
        }

        return $date->format('Y-m-d');
    }

    private function integerSuffix(string $suffix, int $stockCountId): int
    {
        $normalized = ltrim($suffix, '0');
        $normalized = $normalized === '' ? '0' : $normalized;
        $maximum = (string) self::MAX_COUNTER;

        if (strlen($normalized) > strlen($maximum)
            || (strlen($normalized) === strlen($maximum) && strcmp($normalized, $maximum) > 0)) {
            throw new RuntimeException(
                "Cannot initialize the Stock Count number counter: Stock Count ID {$stockCountId} has a suffix greater than the integer counter limit. No Stock Count numbers were changed."
            );
        }

        return (int) $normalized;
    }
};
