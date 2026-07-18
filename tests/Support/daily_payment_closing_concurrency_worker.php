<?php

use App\Models\DailyPaymentClosing;
use App\Models\User;
use App\Services\Sales\DailyPaymentClosingService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$payload = json_decode(base64_decode($argv[1] ?? '', true) ?: '', true, flags: JSON_THROW_ON_ERROR);

if (DB::connection()->getDatabaseName() === 'atrilak_pos' || app()->environment('production')) {
    throw new RuntimeException('Concurrency worker refused a non-test database.');
}

$lock = max(50, min(5000, (int) ($payload['lock_timeout_ms'] ?? 2000)));
$statement = max($lock, min(10000, (int) ($payload['statement_timeout_ms'] ?? 5000)));
DB::statement("SET lock_timeout = '{$lock}ms'");
DB::statement("SET statement_timeout = '{$statement}ms'");
while ((int) floor(microtime(true) * 1000) < (int) ($payload['start_at_ms'] ?? 0)) {
    usleep(1000);
}

try {
    $actor = new User;
    $actor->setRawAttributes(['id' => $payload['actor_id']], true);
    $service = app(DailyPaymentClosingService::class);
    $closing = isset($payload['closing_id']) ? DailyPaymentClosing::findOrFail($payload['closing_id']) : null;
    $result = match ($payload['operation']) {
        'open' => (function () use ($service, $payload, $actor) {
            [$closing] = $service->open($payload['business_date'], $actor);

            return ['ok' => true, 'id' => $closing->id];
        })(),
        'finalize' => ['ok' => true, 'id' => $service->finalize($closing, $payload['revision'], $actor)->id],
        'reopen' => ['ok' => true, 'id' => $service->reopen($closing, 'race', $payload['revision'], $actor)->id],
        'update' => ['ok' => true, 'id' => $service->update($closing, '0.00', '0.00', null, $payload['revision'])->id],
    };
} catch (Throwable $exception) {
    $result = ['ok' => false, 'exception' => $exception::class, 'code' => $exception->getCode()];
}
echo json_encode($result, JSON_THROW_ON_ERROR);
