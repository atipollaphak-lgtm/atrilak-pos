<?php

namespace Tests\Feature\DailyPaymentClosings;

use App\Models\DailyPaymentClosing;
use App\Models\Sale;
use App\Models\User;
use App\Services\Sales\DailyPaymentClosingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class DailyPaymentClosingConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected function beginDatabaseTransaction(): void
    {
        // Child processes need committed rows from the parent process.
    }

    protected function tearDown(): void
    {
        DB::table('daily_payment_closing_sales')->delete();
        DB::table('daily_payment_closings')->delete();
        DB::table('sales')->delete();
        DB::table('users')->delete();

        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL is required for daily closing concurrency tests.');
        }

        if (DB::connection()->getDatabaseName() === 'atrilak_pos') {
            $this->fail('Concurrency tests refused the application database.');
        }

        DB::table('daily_payment_closing_sales')->delete();
        DB::table('daily_payment_closings')->delete();
        DB::table('sales')->delete();
        DB::table('users')->delete();
    }

    public function test_concurrent_open_returns_one_logical_closing_without_unique_errors(): void
    {
        $actor = $this->actor();
        $results = $this->runConcurrently(
            ['operation' => 'open', 'business_date' => '2026-07-18', 'actor_id' => $actor->id],
            ['operation' => 'open', 'business_date' => '2026-07-18', 'actor_id' => $actor->id],
        );

        $this->assertTrue(collect($results)->every(fn (array $result) => $result['ok']), json_encode($results));
        $this->assertSame(1, DailyPaymentClosing::query()->count());
        $this->assertSame(1, collect($results)->pluck('id')->unique()->count());
    }

    public function test_concurrent_finalize_allows_one_transition_and_one_conflict(): void
    {
        $actor = $this->actor();
        $closing = $this->openClosing($actor);
        $sale = $this->sale();
        $results = $this->runConcurrently(
            ['operation' => 'finalize', 'closing_id' => $closing->id, 'revision' => 1, 'actor_id' => $actor->id],
            ['operation' => 'finalize', 'closing_id' => $closing->id, 'revision' => 1, 'actor_id' => $actor->id],
        );

        $this->assertSame(1, collect($results)->where('ok', true)->count(), json_encode($results));
        $this->assertSame(1, collect($results)->where('ok', false)->count(), json_encode($results));
        $final = $closing->fresh();
        $this->assertSame(DailyPaymentClosing::STATUS_FINALIZED, $final->status);
        $this->assertSame(2, $final->revision);
        $this->assertSame($actor->id, $final->finalized_by);
        $this->assertNotNull($final->finalized_at);
        $this->assertSame('10.00', $final->expected_cash_amount);
        $this->assertSame('10.00', $final->actual_cash_amount);
        $this->assertSame(1, $final->sales()->count());
        $this->assertSame($sale->id, $final->sales()->sole()->sale_id);
    }

    public function test_concurrent_reopen_and_finalize_leave_one_consistent_transition(): void
    {
        $actor = $this->actor();
        $closing = $this->openClosing($actor);
        app(DailyPaymentClosingService::class)->finalize($closing, 1, $actor);
        $results = $this->runConcurrently(
            ['operation' => 'reopen', 'closing_id' => $closing->id, 'revision' => 2, 'actor_id' => $actor->id],
            ['operation' => 'finalize', 'closing_id' => $closing->id, 'revision' => 2, 'actor_id' => $actor->id],
        );

        $this->assertSame(1, collect($results)->where('ok', true)->count(), json_encode($results));
        $final = $closing->fresh();
        $this->assertSame(DailyPaymentClosing::STATUS_OPEN, $final->status);
        $this->assertSame(3, $final->revision);
        $this->assertSame($actor->id, $final->reopened_by);
        $this->assertNotNull($final->reopened_at);
        $this->assertSame($actor->id, $final->finalized_by);
    }

    public function test_stale_update_cannot_overwrite_a_finalized_close(): void
    {
        $actor = $this->actor();
        $closing = $this->openClosing($actor);
        app(DailyPaymentClosingService::class)->finalize($closing, 1, $actor);
        $result = $this->runWorker(['operation' => 'update', 'closing_id' => $closing->id, 'revision' => 1, 'actor_id' => $actor->id]);

        $this->assertFalse($result['ok']);
        $final = $closing->fresh();
        $this->assertSame(DailyPaymentClosing::STATUS_FINALIZED, $final->status);
        $this->assertSame(2, $final->revision);
        $this->assertSame('10.00', $final->actual_cash_amount);
        $this->assertSame($actor->id, $final->finalized_by);
    }

    private function actor(): User
    {
        return User::factory()->create(['role' => 'owner']);
    }

    private function openClosing(User $actor): DailyPaymentClosing
    {
        return DailyPaymentClosing::query()->create(['business_date' => '2026-07-18', 'opened_by' => $actor->id, 'actual_cash_amount' => '10.00', 'actual_promptpay_amount' => '0.00']);
    }

    private function sale(): Sale
    {
        return Sale::query()->create(['sale_no' => 'CONCURRENT-CLOSE', 'sale_date' => '2026-07-18', 'total_amount' => '10.00', 'delivery_fee' => '0.00', 'discount' => '0.00', 'delivery_type' => 'pickup', 'status' => 'active', 'payment_method' => 'cash', 'cash_amount' => '10.00', 'promptpay_amount' => '0.00', 'received_amount' => '10.00', 'change_amount' => '0.00']);
    }

    private function runConcurrently(array $first, array $second): array
    {
        $start = (int) floor(microtime(true) * 1000) + 500;
        $processes = [$this->workerProcess($first + ['start_at_ms' => $start]), $this->workerProcess($second + ['start_at_ms' => $start])];
        foreach ($processes as $process) {
            $process->start();
        }

return array_map(fn (Process $process) => $this->workerResult($process), $processes);
    }

    private function runWorker(array $payload): array
    {
        $process = $this->workerProcess($payload);
        $process->run();

        return $this->workerResult($process);
    }

    private function workerProcess(array $payload): Process
    {
        return new Process([PHP_BINARY, base_path('tests/Support/daily_payment_closing_concurrency_worker.php'), base64_encode(json_encode($payload + ['lock_timeout_ms' => 2000, 'statement_timeout_ms' => 5000], JSON_THROW_ON_ERROR))], base_path(), $this->workerEnvironment(), null, 12);
    }

    private function workerResult(Process $process): array
    {
        $process->wait();
        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());

        return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function workerEnvironment(): array
    {
        $connection = config('database.connections.'.config('database.default'));

        return ['APP_ENV' => 'testing', 'APP_KEY' => (string) config('app.key'), 'DB_CONNECTION' => 'pgsql', 'DB_URL' => '', 'DB_HOST' => (string) $connection['host'], 'DB_PORT' => (string) $connection['port'], 'DB_DATABASE' => (string) $connection['database'], 'DB_USERNAME' => (string) $connection['username'], 'DB_PASSWORD' => (string) $connection['password'], 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'SESSION_DRIVER' => 'array'];
    }
}
