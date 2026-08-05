<?php

namespace App\Services;

use App\Models\Unit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UnitCodeService
{
    public function create(array $attributes): Unit
    {
        return DB::transaction(function () use ($attributes): Unit {
            $unit = Unit::query()->create([
                'code' => 'TMP-'.strtoupper(Str::random(20)),
                'name' => $attributes['name'],
                'short_name' => $attributes['short_name'],
                'active' => $attributes['active'] ?? false,
                'sort_order' => $attributes['sort_order'] ?? 0,
            ]);

            $unit->update([
                'code' => $this->generatedCode($unit),
            ]);

            return $unit->fresh();
        });
    }

    private function generatedCode(Unit $unit): string
    {
        $baseCode = 'UNT-'.str_pad((string) $unit->id, 6, '0', STR_PAD_LEFT);
        $code = $baseCode;
        $suffix = 1;

        while (Unit::query()
            ->where('code', $code)
            ->whereKeyNot($unit->id)
            ->exists()) {
            $code = $baseCode.'-'.$suffix;
            $suffix++;
        }

        return $code;
    }
}
