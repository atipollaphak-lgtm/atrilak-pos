<?php

namespace Tests\Unit\Services\Sales;

use App\Services\Sales\SalePriceSnapshotService;
use DomainException;
use PHPUnit\Framework\TestCase;

class SalePriceSnapshotServiceTest extends TestCase
{
    public function test_unedited_price_uses_system_price_without_snapshot(): void
    {
        $service = new SalePriceSnapshotService;

        $this->assertSame([
            'selling_price' => '100.00',
            'original_price' => null,
            'price_override_flag' => false,
        ], $service->snapshot('100.00', '100.00', false));
    }

    public function test_edited_price_snapshots_system_price_and_marks_override(): void
    {
        $service = new SalePriceSnapshotService;

        $this->assertSame([
            'selling_price' => '99.50',
            'original_price' => '100.00',
            'price_override_flag' => true,
        ], $service->snapshot('100.00', '99.50', true));
    }

    public function test_invalid_requested_price_is_rejected(): void
    {
        $this->expectException(DomainException::class);

        (new SalePriceSnapshotService)->snapshot('100.00', '99.999', true);
    }
}
