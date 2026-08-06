<?php

namespace App\Services;

use App\Models\Unit;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UnitCodeService
{
    public function create(array $attributes): Unit
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                return DB::transaction(function () use ($attributes): Unit {
                    $unit = Unit::query()->create([
                        'code' => $this->temporaryCode(),
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
            } catch (QueryException $exception) {
                if (! $this->isCodeCollision($exception) || $attempt === 4) {
                    throw $exception;
                }
            }
        }

        throw new \LogicException('Unit code generation exhausted its retry budget.');
    }

    protected function temporaryCode(): string
    {
        return 'TMP-'.strtoupper(Str::random(20));
    }

    private function isCodeCollision(QueryException $exception): bool
    {
        $state = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        $message = strtolower($exception->getMessage());

        return in_array($state, ['23000', '23505'], true)
            && str_contains($message, 'code');
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
