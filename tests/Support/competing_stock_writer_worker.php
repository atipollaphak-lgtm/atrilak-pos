<?php

use App\Models\Product;
use App\Models\Purchase;
use App\Models\Quotation;
use App\Models\Sale;
use App\Services\ProductUpdateService;
use App\Services\PurchaseService;
use App\Services\SaleService;
use App\Services\StockCountService;
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
    $operation = $payload['operation'] ?? null;
    $result = match ($operation) {
        'sale_create' => ['sale_id' => app(SaleService::class)->createSale($payload['data'])->id],
        'sale_update' => ['sale_id' => app(SaleService::class)->updateSale(
            Sale::findOrFail($payload['sale_id']),
            $payload['data']
        )->id],
        'sale_delete' => tap([], fn () => app(SaleService::class)->deleteSale(
            Sale::findOrFail($payload['sale_id'])
        )),
        'purchase_create' => ['purchase_id' => app(PurchaseService::class)->create($payload['data'])->id],
        'purchase_update' => ['purchase_id' => app(PurchaseService::class)->update(
            Purchase::findOrFail($payload['purchase_id']),
            $payload['data']
        )->id],
        'purchase_delete' => tap([], fn () => app(PurchaseService::class)->delete(
            Purchase::findOrFail($payload['purchase_id'])
        )),
        'stock_count' => ['stock_count_id' => app(StockCountService::class)->create($payload['data'])->id],
        'product_update' => ['product_id' => app(ProductUpdateService::class)->update(
            Product::findOrFail($payload['product_id']),
            $payload['data']
        )->id],
        'quotation_convert' => ['sale_id' => app(SaleService::class)->createSaleFromQuotation(
            Quotation::findOrFail($payload['quotation_id'])
        )->id],
        default => throw new InvalidArgumentException('Unknown worker operation.'),
    };
    $result = ['ok' => true] + $result;
} catch (Throwable $exception) {
    $result = [
        'ok' => false,
        'exception' => $exception::class,
        'message' => $exception->getMessage(),
    ];
}

echo json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
