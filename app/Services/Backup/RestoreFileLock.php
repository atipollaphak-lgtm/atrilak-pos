<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\File;

final class RestoreFileLock
{
    private mixed $handle = null;

    private ?string $reasonCode = null;

    public function __construct(private readonly string $path) {}

    public function acquire(array $metadata): bool
    {
        try {
            File::ensureDirectoryExists(dirname($this->path));
        } catch (\Throwable) {
            return $this->fail('lock_directory_unavailable');
        }

        $handle = @fopen($this->path, 'c+');
        if ($handle === false) {
            return $this->fail('lock_open_failed');
        }

        if (! @flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return $this->fail('lock_held');
        }

        $payload = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        if ($payload === false || ! @ftruncate($handle, 0) || @fwrite($handle, $payload) === false || ! @fflush($handle)) {
            @flock($handle, LOCK_UN);
            @fclose($handle);

            return $this->fail('lock_write_failed');
        }

        $this->handle = $handle;
        $this->reasonCode = null;

        return true;
    }

    public function release(): bool
    {
        if (! is_resource($this->handle)) {
            return true;
        }

        $handle = $this->handle;
        $this->handle = null;

        if (! @flock($handle, LOCK_UN) || ! @fclose($handle)) {
            $this->reasonCode = 'lock_release_failed';

            return false;
        }

        return true;
    }

    public function reasonCode(): ?string
    {
        return $this->reasonCode;
    }

    private function fail(string $reasonCode): bool
    {
        $this->reasonCode = $reasonCode;

        return false;
    }
}
