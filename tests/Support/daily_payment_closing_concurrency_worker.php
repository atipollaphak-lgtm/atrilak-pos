<?php

use App\Models\DailyPaymentClosing;
use App\Models\Sale;
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
        'sale_create' => ['ok' => true, 'id' => Sale::query()->create([
            'sale_no' => 'RACE-CREATED-'.uniqid(),
            'sale_date' => '2026-07-18',
            'total_amount' => '2.00',
            'delivery_fee' => '0.00',
            'discount' => '0.00',
            'delivery_type' => 'pickup',
            'status' => Sale::STATUS_ACTIVE,
            'revision' => 1,
            'payment_method' => 'cash',
            'cash_amount' => '2.00',
            'promptpay_amount' => '0.00',
            'received_amount' => '2.00',
            'change_amount' => '0.00',
        ])->id],
        'sale_update' => (function () use ($payload): array {
            $sale = Sale::query()->findOrFail($payload['sale_id']);
            $sale->forceFill([
                'revision' => (int) $sale->revision + 1,
                'total_amount' => '12.00',
                'cash_amount' => '12.00',
                'received_amount' => '12.00',
            ])->save();

            return ['ok' => true, 'id' => $sale->id];
        })(),
        'sale_void' => (function () use ($payload): array {
            $sale = Sale::query()->findOrFail($payload['sale_id']);
            $sale->forceFill([
                'status' => Sale::STATUS_VOIDED,
                'revision' => (int) $sale->revision + 1,
            ])->save();

            return ['ok' => true, 'id' => $sale->id];
        })(),
    };
} catch (Throwable $exception) {
    $result = ['ok' => false, 'exception' => $exception::class, 'code' => $exception->getCode()];
}
echo json_encode($result, JSON_THROW_ON_ERROR);
