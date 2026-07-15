<?php

namespace Tests\Unit\Support;

use App\Support\DocumentSnapshotValue;
use PHPUnit\Framework\TestCase;

class DocumentSnapshotValueTest extends TestCase
{
    public function test_snapshot_takes_priority_over_live_value(): void
    {
        $this->assertSame(
            'Snapshot Value',
            DocumentSnapshotValue::resolve('Snapshot Value', 'Live Value')
        );
    }

    public function test_null_snapshot_uses_live_value_then_safe_fallback(): void
    {
        $this->assertSame(
            'Live Value',
            DocumentSnapshotValue::resolve(null, 'Live Value')
        );
        $this->assertSame('-', DocumentSnapshotValue::resolve(null, null));
        $this->assertSame('', DocumentSnapshotValue::resolve(null, null, ''));
    }

    public function test_empty_string_and_zero_snapshots_are_not_treated_as_missing(): void
    {
        $this->assertSame('', DocumentSnapshotValue::resolve('', 'Live Value'));
        $this->assertSame('0', DocumentSnapshotValue::resolve('0', 'Live Value'));
        $this->assertSame(0, DocumentSnapshotValue::resolve(0, 'Live Value'));
    }
}
