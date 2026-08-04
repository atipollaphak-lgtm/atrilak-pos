<?php

namespace App\Support;

use RuntimeException;

final class DatabaseEnvironmentGuard
{
    public const PRODUCTION_DATABASE = 'atrilak_pos_production';

    public static function assertSafeForTests(string $environment, string $database): void
    {
        if ($environment !== 'testing') {
            throw new RuntimeException('Tests refused: APP_ENV must be testing.');
        }

        if (self::isProductionDatabase($database)) {
            throw new RuntimeException(
                'Tests refused: database connection points to '.self::PRODUCTION_DATABASE
            );
        }
    }

    public static function assertTestDatabase(string $environment, string $database): void
    {
        if (! in_array($environment, ['local', 'testing'], true)) {
            throw new RuntimeException('Test fixtures refused: APP_ENV must be local or testing.');
        }

        if (self::isProductionDatabase($database)) {
            throw new RuntimeException(
                'Test fixtures refused: database connection points to '.self::PRODUCTION_DATABASE
            );
        }

        if (preg_match('/(?:^|[_-])(test|testing|browser)(?:[_-]|$)/i', trim($database)) !== 1) {
            throw new RuntimeException('Test fixtures require a clearly named test database.');
        }
    }

    private static function isProductionDatabase(string $database): bool
    {
        return strtolower(trim($database)) === self::PRODUCTION_DATABASE;
    }
}
