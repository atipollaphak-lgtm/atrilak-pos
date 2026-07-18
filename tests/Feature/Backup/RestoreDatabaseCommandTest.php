<?php

namespace Tests\Feature\Backup;

use App\Services\Backup\DatabaseRestoreResult;
use App\Services\Backup\DatabaseRestoreService;
use Tests\TestCase;

class RestoreDatabaseCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'database.default' => 'pgsql',
            'database.connections.pgsql.database' => 'atrilak_pos_restore_test',
        ]);
    }

    public function test_command_is_discovered_and_interactive_confirmation_calls_service(): void
    {
        $service = $this->fakeService(DatabaseRestoreResult::success('pre-restore.sql', 'staged.sql'));

        $this->artisan('atrilak:restore', ['file' => 'C:\\restore.sql'])
            ->expectsOutput('ฐานข้อมูลเป้าหมาย: atrilak_pos_restore_test')
            ->expectsQuestion('กรุณาพิมพ์ "RESTORE atrilak_pos_restore_test" เพื่อยืนยัน', 'RESTORE atrilak_pos_restore_test')
            ->expectsOutput('การกู้คืนฐานข้อมูลเสร็จสิ้น')
            ->expectsOutput('ระบบยังอยู่ในโหมดบำรุงรักษา')
            ->assertExitCode(0);

        $this->assertSame(['C:\\restore.sql'], $service->paths);
    }

    public function test_interactive_empty_or_mismatched_confirmation_does_not_call_service(): void
    {
        $service = $this->fakeService(DatabaseRestoreResult::success('pre.sql', 'staged.sql'));

        $this->artisan('atrilak:restore', ['file' => 'restore.sql'])
            ->expectsQuestion('กรุณาพิมพ์ "RESTORE atrilak_pos_restore_test" เพื่อยืนยัน', '')
            ->expectsOutput('การยืนยันไม่ถูกต้อง จึงยกเลิกการกู้คืน')
            ->assertExitCode(2);

        $this->assertSame([], $service->paths);
    }

    public function test_non_interactive_confirmation_requires_an_exact_option_and_preserves_file_argument(): void
    {
        $service = $this->fakeService(DatabaseRestoreResult::success('pre.sql', 'staged.sql'));

        $this->artisan('atrilak:restore', [
            'file' => 'relative\\restore.sql',
            '--confirm' => 'RESTORE atrilak_pos_restore_test',
        ])->assertExitCode(0);

        $this->assertSame(['relative\\restore.sql'], $service->paths);

        $this->artisan('atrilak:restore', ['file' => 'restore.sql', '--no-interaction' => true])
            ->expectsOutput('การยืนยันไม่ถูกต้อง จึงยกเลิกการกู้คืน')
            ->assertExitCode(2);
        $this->assertSame(['relative\\restore.sql'], $service->paths);
    }

    public function test_rejection_and_operational_reason_codes_map_to_safe_exit_codes(): void
    {
        $invalid = $this->fakeService(DatabaseRestoreResult::failure('source_missing'));
        $this->artisan('atrilak:restore', ['file' => 'missing.sql', '--confirm' => 'RESTORE atrilak_pos_restore_test'])
            ->expectsOutput('ไม่สามารถเริ่มการกู้คืนได้ กรุณาตรวจสอบไฟล์และการตั้งค่า')
            ->assertExitCode(2);

        $operational = $this->fakeService(DatabaseRestoreResult::failure('process_non_zero', maintenanceState: 'down', partialRestoreRisk: true));
        $this->artisan('atrilak:restore', ['file' => 'broken.sql', '--confirm' => 'RESTORE atrilak_pos_restore_test'])
            ->expectsOutput('การกู้คืนไม่เสร็จสมบูรณ์ ฐานข้อมูลอาจถูกแก้ไขบางส่วน')
            ->assertExitCode(1);

        $this->assertSame(['broken.sql'], $operational->paths);
    }

    public function test_process_start_recovery_and_cleanup_warning_are_safe(): void
    {
        $service = $this->fakeService(
            DatabaseRestoreResult::failure('process_start_failed', maintenanceState: 'up')
        );
        $this->artisan('atrilak:restore', ['file' => 'start.sql', '--confirm' => 'RESTORE atrilak_pos_restore_test'])
            ->expectsOutput('ไม่สามารถเริ่มโปรแกรมกู้คืนฐานข้อมูลได้')
            ->expectsOutput('ฐานข้อมูลยังไม่ถูกแก้ไขและระบบถูกนำออกจากโหมดบำรุงรักษาแล้ว')
            ->assertExitCode(1);

        $warning = $this->fakeService(
            DatabaseRestoreResult::success('safe-pre-backup.sql', 'C:\\private\\staged.sql')->withCleanupSucceeded(false)
        );
        $this->artisan('atrilak:restore', ['file' => 'cleanup.sql', '--confirm' => 'RESTORE atrilak_pos_restore_test'])
            ->expectsOutput('การกู้คืนเสร็จสิ้น แต่ไม่สามารถล้างไฟล์ชั่วคราวได้ทั้งหมด')
            ->doesntExpectOutputToContain('C:\\private\\staged.sql')
            ->assertExitCode(0);

        $this->assertSame(['cleanup.sql'], $warning->paths);
    }

    public function test_service_exception_returns_a_generic_safe_failure(): void
    {
        $service = new class extends DatabaseRestoreService
        {
            public function __construct() {}

            public function restore(string $sourcePath): DatabaseRestoreResult
            {
                throw new \RuntimeException('password=secret C:\\internal-path');
            }
        };
        $this->app->instance(DatabaseRestoreService::class, $service);

        $this->artisan('atrilak:restore', ['file' => 'exception.sql', '--confirm' => 'RESTORE atrilak_pos_restore_test'])
            ->expectsOutput('ไม่สามารถดำเนินการกู้คืนฐานข้อมูลได้ กรุณาตรวจสอบ log และคู่มือการกู้คืน')
            ->doesntExpectOutputToContain('secret')
            ->doesntExpectOutputToContain('internal-path')
            ->assertExitCode(1);
    }

    private function fakeService(DatabaseRestoreResult $result): object
    {
        $service = new class($result) extends DatabaseRestoreService
        {
            public array $paths = [];

            public function __construct(private DatabaseRestoreResult $result) {}

            public function restore(string $sourcePath): DatabaseRestoreResult
            {
                $this->paths[] = $sourcePath;

                return $this->result;
            }
        };
        $this->app->instance(DatabaseRestoreService::class, $service);

        return $service;
    }
}
