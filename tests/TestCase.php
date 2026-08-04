<?php

namespace Tests;

use App\Support\DatabaseEnvironmentGuard;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DatabaseEnvironmentGuard::assertSafeForTests(
            app()->environment(),
            (string) DB::connection()->getDatabaseName()
        );
    }
}
