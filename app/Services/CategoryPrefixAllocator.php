<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Allocates missing category prefixes while preserving every prefix supplied
 * by a user. Callers may use this service inside a larger transaction; the
 * nested transaction is safe in Laravel and keeps the advisory lock scoped to
 * the outer PostgreSQL transaction.
 */
class CategoryPrefixAllocator
{
    private const MAX_BARCODE_PREFIX = 999;

    public function ensure(Category $category): Category
    {
        return DB::transaction(function () use ($category): Category {
            $locked = Category::query()->lockForUpdate()->findOrFail($category->getKey());
            // All catalog writers lock the category row before taking the
            // shared allocator lock. Keeping that order avoids a cycle when
            // a direct allocator call overlaps a category/product update.
            $this->lockAllocation();

            $updates = [];
            if (blank($locked->code_prefix)) {
                $updates['code_prefix'] = $this->nextCodePrefix($locked);
            }
            if (blank($locked->barcode_prefix)) {
                $updates['barcode_prefix'] = $this->nextBarcodePrefix();
            }

            if ($updates !== []) {
                $locked->forceFill($updates)->save();
            }

            return $locked->fresh();
        });
    }

    private function nextCodePrefix(Category $category): string
    {
        $base = $this->deriveCodePrefix((string) $category->name);
        $base = $base !== '' ? $base : 'CAT';

        if (! Category::query()->where('code_prefix', $base)->exists()) {
            return $base;
        }

        for ($suffixNumber = 1; $suffixNumber <= 702; $suffixNumber++) {
            $suffix = $this->suffix($suffixNumber);
            $candidate = substr($base, 0, max(1, 20 - strlen($suffix))).$suffix;
            if (! Category::query()->where('code_prefix', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw ValidationException::withMessages([
            'category_id' => 'ไม่สามารถสร้าง Code Prefix เพิ่มได้เนื่องจาก Prefix ซ้ำเต็มจำนวนที่รองรับ',
        ]);
    }

    private function nextBarcodePrefix(): string
    {
        $used = Category::query()
            ->whereNotNull('barcode_prefix')
            ->pluck('barcode_prefix')
            ->map(static fn ($value): int => (int) $value)
            ->flip();

        for ($value = 101; $value <= self::MAX_BARCODE_PREFIX; $value++) {
            if (! isset($used[$value])) {
                return str_pad((string) $value, 3, '0', STR_PAD_LEFT);
            }
        }

        throw ValidationException::withMessages([
            'category_id' => 'ไม่สามารถสร้าง Barcode Prefix เพิ่มได้เนื่องจากเลข 101-999 ถูกใช้ครบแล้ว',
        ]);
    }

    private function deriveCodePrefix(string $name): string
    {
        $thaiCodeMap = [
            'ก' => 'K', 'ข' => 'K', 'ค' => 'K', 'ง' => 'N', 'จ' => 'J',
            'ช' => 'C', 'ซ' => 'S', 'ด' => 'D', 'ต' => 'T', 'ถ' => 'T',
            'ท' => 'T', 'น' => 'N', 'บ' => 'B', 'ป' => 'P', 'ผ' => 'P',
            'พ' => 'P', 'ฟ' => 'F', 'ม' => 'M', 'ย' => 'Y', 'ร' => 'R',
            'ล' => 'L', 'ว' => 'W', 'ศ' => 'S', 'ส' => 'S', 'ห' => 'H',
            'อ' => '', 'ฮ' => 'H',
        ];

        preg_match_all('/[A-Za-z]/', $name, $latinMatches);
        $latin = strtoupper(implode('', $latinMatches[0] ?? []));
        if ($latin !== '') {
            return substr($latin, 0, 3);
        }

        $thai = '';
        foreach (preg_split('//u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
            $thai .= $thaiCodeMap[$character] ?? '';
        }

        return strtoupper(substr($thai, -3));
    }

    private function suffix(int $number): string
    {
        $suffix = '';
        while ($number > 0) {
            $number--;
            $suffix = chr(65 + ($number % 26)).$suffix;
            $number = intdiv($number, 26);
        }

        return $suffix;
    }

    private function lockAllocation(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::select("select pg_advisory_xact_lock(hashtext('atrilak:category-prefix'))");
        }
    }
}
