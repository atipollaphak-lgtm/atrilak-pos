<?php

namespace Tests\Unit\Support;

use App\Support\DatabaseEnvironmentGuard;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DatabaseEnvironmentGuardTest extends TestCase
{
    public function test_testing_runtime_allows_a_non_production_database(): void
    {
        DatabaseEnvironmentGuard::assertSafeForTests('testing', 'atrilak_pos_final_test_20260729');

        $this->assertTrue(true);
    }

    public function test_testing_runtime_rejects_the_production_database(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tests refused: database connection points to atrilak_pos_production');

        DatabaseEnvironmentGuard::assertSafeForTests('testing', 'atrilak_pos_production');
    }

    public function test_fixture_runtime_rejects_a_database_without_a_test_marker(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Test fixtures require a clearly named test database');

        DatabaseEnvironmentGuard::assertTestDatabase('local', 'atrilak_pos');
    }

    public function test_fixture_runtime_allows_the_approved_local_test_database(): void
    {
        DatabaseEnvironmentGuard::assertTestDatabase('local', 'atrilak_pos_final_test_20260729');

        $this->assertTrue(true);
    }
}
