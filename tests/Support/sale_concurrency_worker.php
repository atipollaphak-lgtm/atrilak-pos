<?php

use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$payload = json_decode(base64_decode($argv[1] ?? '', true) ?: '', true, flags: JSON_THROW_ON_ERROR);
$database = (string) DB::connection()->getDatabaseName();

if ($database === '' || $database === 'atrilak_pos' || app()->environment('production')) {
    throw new RuntimeException('Concurrency worker refused a non-test database.');
}

$lockTimeout = max(50, min(5000, (int) ($payload['lock_timeout_ms'] ?? 2000)));
$statementTimeout = max($lockTimeout, min(10000, (int) ($payload['statement_timeout_ms'] ?? 5000)));

DB::statement("SET lock_timeout = '{$lockTimeout}ms'");
DB::statement("SET statement_timeout = '{$statementTimeout}ms'");

$startAt = (int) ($payload['start_at_ms'] ?? 0);

while ($startAt > 0 && (int) floor(microtime(true) * 1000) < $startAt) {
    usleep(1000);
}

try {
    $service = app(SaleService::class);
    $operation = $payload['operation'] ?? null;

    if ($operation === 'create') {
        $sale = $service->createSale($payload['data']);
        $result = [
            'ok' => true,
            'sale_id' => $sale->id,
            'sale_no' => $sale->sale_no,
            'idempotent_replay' => $sale->idempotentReplay,
        ];
    } elseif ($operation === 'update') {
        $sale = Sale::findOrFail($payload['sale_id']);
        $updated = $service->updateSale($sale, $payload['data']);
        $result = ['ok' => true, 'sale_id' => $updated->id];
    } elseif ($operation === 'delete') {
        $sale = Sale::findOrFail($payload['sale_id']);
        $service->deleteSale($sale);
        $result = ['ok' => true, 'sale_id' => (int) $payload['sale_id']];
    } else {
        throw new InvalidArgumentException('Unknown worker operation.');
    }
} catch (Throwable $exception) {
    $result = [
        'ok' => false,
        'exception' => $exception::class,
        'message' => $exception->getMessage(),
    ];
}

echo json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
