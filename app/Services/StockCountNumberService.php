<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class StockCountNumberService
{
    public function generate(string $countDate): string
    {
        $runningNumber = DB::getDriverName() === 'pgsql'
            ? $this->nextPostgreSqlNumber($countDate)
            : $this->nextPortableNumber($countDate);

        return 'SC-'.str_replace('-', '', $countDate).'-'.str_pad(
            (string) $runningNumber,
            4,
            '0',
            STR_PAD_LEFT
        );
    }

    private function nextPostgreSqlNumber(string $countDate): int
    {
        $counter = DB::selectOne(<<<'SQL'
INSERT INTO stock_count_number_counters
    (count_date, last_number, created_at, updated_at)
VALUES (?, 1, NOW(), NOW())
ON CONFLICT (count_date)
DO UPDATE SET
    last_number = stock_count_number_counters.last_number + 1,
    updated_at = NOW()
RETURNING last_number
SQL, [$countDate]);

        return (int) $counter->last_number;
    }

    private function nextPortableNumber(string $countDate): int
    {
        $inserted = DB::table('stock_count_number_counters')->insertOrIgnore([
            'count_date' => $countDate,
            'last_number' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($inserted === 1) {
            return 1;
        }

        DB::table('stock_count_number_counters')
            ->where('count_date', $countDate)
            ->increment('last_number', 1, ['updated_at' => now()]);

        return (int) DB::table('stock_count_number_counters')
            ->where('count_date', $countDate)
            ->value('last_number');
    }
}
