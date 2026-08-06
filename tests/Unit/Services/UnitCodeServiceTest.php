<?php

namespace Tests\Unit\Services;

use App\Models\Unit;
use App\Services\UnitCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitCodeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_temporary_code_collision_is_retried_and_final_code_remains_unique(): void
    {
        Unit::query()->create([
            'code' => 'TMP-COLLISION',
            'name' => 'Existing unit',
            'short_name' => 'EX',
            'active' => true,
            'sort_order' => 1,
        ]);

        $service = new class extends UnitCodeService
        {
            public int $attempts = 0;

            protected function temporaryCode(): string
            {
                $this->attempts++;

                return $this->attempts === 1
                    ? 'TMP-COLLISION'
                    : 'TMP-UNIQUE';
            }
        };

        $unit = $service->create([
            'name' => 'Generated unit',
            'short_name' => 'GEN',
            'active' => true,
            'sort_order' => 2,
        ]);

        $this->assertSame(2, $service->attempts);
        $this->assertMatchesRegularExpression('/^UNT-\d{6}(?:-\d+)?$/', $unit->code);
        $this->assertSame(1, Unit::query()->where('code', $unit->code)->count());
    }
}
