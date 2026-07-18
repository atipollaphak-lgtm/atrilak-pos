<?php

return [
    'pg_dump_path' => env('PG_DUMP_PATH'),
    'psql_path' => env('PSQL_PATH'),
    'directory' => storage_path('app/backups'),
    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 30),
    'lock_seconds' => (int) env('BACKUP_LOCK_SECONDS', 3600),
    'process_timeout_seconds' => (int) env('BACKUP_PROCESS_TIMEOUT_SECONDS', 900),
    'restore_enabled' => env('BACKUP_RESTORE_ENABLED', false),
    'restore_max_kb' => (int) env('BACKUP_RESTORE_MAX_KB', 512000),
    'restore_timeout_seconds' => (int) env('BACKUP_RESTORE_TIMEOUT_SECONDS', 1800),
];
