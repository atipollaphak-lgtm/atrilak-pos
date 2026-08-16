<?php

namespace App\Services\Customers;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class CustomerCodeAllocator
{
    /**
     * Reserve the next customer codes inside the caller's transaction.
     *
     * @return list<string>
     */
    public function reserveCodes(int $count): array
    {
        if ($count <= 0) {
            return [];
        }

        $this->lockAllocation();

        $codes = Customer::query()
            ->lockForUpdate()
            ->pluck('code');

        $next = $codes->reduce(function (int $carry, ?string $code): int {
            if (preg_match('/^CUS-(\d+)$/', (string) $code, $matches) === 1) {
                return max($carry, (int) $matches[1]);
            }

            return $carry;
        }, 0) + 1;

        $reserved = [];
        for ($index = 0; $index < $count; $index++) {
            $reserved[] = 'CUS-'.str_pad((string) ($next + $index), 4, '0', STR_PAD_LEFT);
        }

        return $reserved;
    }

    public function nextCode(): string
    {
        return $this->reserveCodes(1)[0];
    }

    private function lockAllocation(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::select("select pg_advisory_xact_lock(hashtext('atrilak:customer-code'))");
        }
    }
}
