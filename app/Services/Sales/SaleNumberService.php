<?php

namespace App\Services\Sales;

use Illuminate\Support\Facades\DB;

class SaleNumberService
{
    public function generate(string $saleDate): string
    {
        $runningNumber = DB::getDriverName() === 'pgsql'
            ? $this->nextPostgreSqlNumber($saleDate)
            : $this->nextPortableNumber($saleDate);

        return 'SAL-'.date('Ymd', strtotime($saleDate)).'-'.str_pad($runningNumber, 4, '0', STR_PAD_LEFT);
    }

    private function nextPostgreSqlNumber(string $saleDate): int
    {
        $counter = DB::selectOne(<<<'SQL'
INSERT INTO sale_number_counters (sale_date, last_number, created_at, updated_at)
VALUES (?, 1, NOW(), NOW())
ON CONFLICT (sale_date)
DO UPDATE SET
    last_number = sale_number_counters.last_number + 1,
    updated_at = NOW()
RETURNING last_number
SQL, [$saleDate]);

        return (int) $counter->last_number;
    }

    private function nextPortableNumber(string $saleDate): int
    {
        $inserted = DB::table('sale_number_counters')->insertOrIgnore([
            'sale_date' => $saleDate,
            'last_number' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($inserted === 1) {
            return 1;
        }

        DB::table('sale_number_counters')
            ->where('sale_date', $saleDate)
            ->increment('last_number', 1, ['updated_at' => now()]);

        return (int) DB::table('sale_number_counters')
            ->where('sale_date', $saleDate)
            ->value('last_number');
    }
}
