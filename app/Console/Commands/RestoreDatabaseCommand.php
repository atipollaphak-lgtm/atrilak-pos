<?php

namespace App\Console\Commands;

use App\Services\Backup\DatabaseRestoreResult;
use App\Services\Backup\DatabaseRestoreService;
use Illuminate\Console\Command;
use Throwable;

class RestoreDatabaseCommand extends Command
{
    protected $signature = 'atrilak:restore
        {file : Path to the SQL backup file}
        {--confirm= : Exact confirmation phrase for non-interactive use}';

    protected $description = 'Restore the configured PostgreSQL database from a SQL backup';

    public function handle(DatabaseRestoreService $databaseRestoreService): int
    {
        $database = (string) config('database.connections.'.config('database.default').'.database');
        $phrase = 'RESTORE '.$database;

        if (! $this->confirmed($database, $phrase)) {
            $this->error('การยืนยันไม่ถูกต้อง จึงยกเลิกการกู้คืน');

            return 2;
        }

        try {
            $result = $databaseRestoreService->restore($this->argument('file'));
        } catch (Throwable) {
            $this->error('ไม่สามารถดำเนินการกู้คืนฐานข้อมูลได้ กรุณาตรวจสอบ log และคู่มือการกู้คืน');

            return self::FAILURE;
        }

        return $this->report($result);
    }

    private function confirmed(string $database, string $phrase): bool
    {
        $confirmation = $this->option('confirm');
        if ($confirmation !== null) {
            return is_string($confirmation) && hash_equals($phrase, $confirmation);
        }

        if (! $this->input->isInteractive()) {
            return false;
        }

        $this->line('ฐานข้อมูลเป้าหมาย: '.$database);
        $this->warn('การดำเนินการนี้จะแทนที่ข้อมูลในฐานข้อมูลเป้าหมาย');
        $this->warn('ระบบจะคงอยู่ในโหมดบำรุงรักษาหลังการกู้คืน');

        return hash_equals($phrase, (string) $this->ask('กรุณาพิมพ์ "'.$phrase.'" เพื่อยืนยัน'));
    }

    private function report(DatabaseRestoreResult $result): int
    {
        if ($result->successful()) {
            $this->info('การกู้คืนฐานข้อมูลเสร็จสิ้น');
            $this->info('ระบบยังอยู่ในโหมดบำรุงรักษา');
            if ($result->preBackupFileName()) {
                $this->line('ไฟล์สำรองก่อนกู้คืน: '.basename($result->preBackupFileName()));
            }
            if (! $result->cleanupSucceeded()) {
                $this->warn('การกู้คืนเสร็จสิ้น แต่ไม่สามารถล้างไฟล์ชั่วคราวได้ทั้งหมด');
                $this->warn('กรุณาตรวจสอบคู่มือและ log');
            }
            $this->line('กรุณาตรวจสอบระบบก่อนรัน php artisan up');

            return self::SUCCESS;
        }

        if ($result->reasonCode() === 'process_start_failed' && $result->maintenanceState() === 'up') {
            $this->error('ไม่สามารถเริ่มโปรแกรมกู้คืนฐานข้อมูลได้');
            $this->error('ฐานข้อมูลยังไม่ถูกแก้ไขและระบบถูกนำออกจากโหมดบำรุงรักษาแล้ว');

            return self::FAILURE;
        }

        if ($result->partialRestoreRisk()) {
            $this->error('การกู้คืนไม่เสร็จสมบูรณ์ ฐานข้อมูลอาจถูกแก้ไขบางส่วน');
            $this->error('ระบบยังอยู่ในโหมดบำรุงรักษา');
            $this->error('กรุณาตรวจสอบ log และคู่มือการกู้คืน');

            return self::FAILURE;
        }

        if (str_starts_with($result->reasonCode(), 'pre_backup_')) {
            $this->error('การสำรองข้อมูลก่อนกู้คืนไม่สำเร็จ จึงยังไม่ได้เริ่มการกู้คืน');

            return self::FAILURE;
        }

        if (str_starts_with($result->reasonCode(), 'lock_')) {
            $this->error('มีการกู้คืนฐานข้อมูลกำลังทำงานอยู่');

            return 2;
        }

        if ($this->isPreflightRejection($result->reasonCode())) {
            $this->error('ไม่สามารถเริ่มการกู้คืนได้ กรุณาตรวจสอบไฟล์และการตั้งค่า');

            return 2;
        }

        $this->error('ไม่สามารถดำเนินการกู้คืนฐานข้อมูลได้ กรุณาตรวจสอบ log และคู่มือการกู้คืน');

        return self::FAILURE;
    }

    private function isPreflightRejection(string $reasonCode): bool
    {
        return $reasonCode === 'restore_disabled'
            || $reasonCode === 'connection_not_pgsql'
            || $reasonCode === 'psql_executable_missing'
            || str_starts_with($reasonCode, 'source_')
            || str_starts_with($reasonCode, 'staging_')
            || in_array($reasonCode, ['lock_directory_unavailable', 'lock_open_failed', 'lock_write_failed'], true);
    }
}
