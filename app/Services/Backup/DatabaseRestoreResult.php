<?php

namespace App\Services\Backup;

final readonly class DatabaseRestoreResult
{
    private function __construct(
        private bool $success,
        private string $reasonCode,
        private ?int $exitCode,
        private ?string $preBackupFileName,
        private ?string $stagedFileName,
        private string $maintenanceState,
        private bool $partialRestoreRisk,
        private bool $cleanupSucceeded,
        private string $safeMessageCode,
    ) {}

    public static function success(?string $preBackupFileName, ?string $stagedFileName): self
    {
        return new self(true, 'success', 0, $preBackupFileName, $stagedFileName, 'down', false, true, 'restore_success');
    }

    public static function failure(
        string $reasonCode,
        ?string $stagedFileName = null,
        ?string $preBackupFileName = null,
        ?int $exitCode = null,
        string $maintenanceState = 'up',
        bool $partialRestoreRisk = false,
    ): self {
        return new self(false, $reasonCode, $exitCode, $preBackupFileName, $stagedFileName, $maintenanceState, $partialRestoreRisk, true, $reasonCode);
    }

    public function withCleanupSucceeded(bool $cleanupSucceeded): self
    {
        return new self($this->success, $this->reasonCode, $this->exitCode, $this->preBackupFileName, $this->stagedFileName, $this->maintenanceState, $this->partialRestoreRisk, $cleanupSucceeded, $this->safeMessageCode);
    }

    public function successful(): bool
    {
        return $this->success;
    }

    public function reasonCode(): string
    {
        return $this->reasonCode;
    }

    public function exitCode(): ?int
    {
        return $this->exitCode;
    }

    public function preBackupFileName(): ?string
    {
        return $this->preBackupFileName;
    }

    public function stagedFileName(): ?string
    {
        return $this->stagedFileName;
    }

    public function maintenanceState(): string
    {
        return $this->maintenanceState;
    }

    public function partialRestoreRisk(): bool
    {
        return $this->partialRestoreRisk;
    }

    public function cleanupSucceeded(): bool
    {
        return $this->cleanupSucceeded;
    }

    public function safeMessageCode(): string
    {
        return $this->safeMessageCode;
    }
}
