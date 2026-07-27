<?php

namespace App\Services\Backup;

final readonly class DatabaseBackupResult
{
    private function __construct(
        private bool $success,
        private string $reasonCode,
        private ?string $fileName = null,
        private ?int $exitCode = null,
        private ?string $warningCode = null,
    ) {}

    public static function success(string $fileName, ?string $warningCode = null): self
    {
        return new self(true, 'success', $fileName, warningCode: $warningCode);
    }

    public static function failure(string $reasonCode, ?int $exitCode = null): self
    {
        return new self(false, $reasonCode, exitCode: $exitCode);
    }

    public static function skipped(string $reasonCode): self
    {
        return new self(false, $reasonCode);
    }

    public function successful(): bool
    {
        return $this->success;
    }

    public function reasonCode(): string
    {
        return $this->reasonCode;
    }

    public function fileName(): ?string
    {
        return $this->fileName;
    }

    public function exitCode(): ?int
    {
        return $this->exitCode;
    }

    public function warningCode(): ?string
    {
        return $this->warningCode;
    }
}
