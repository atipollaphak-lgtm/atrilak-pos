<?php

namespace App\Services\Products;

use App\Data\Products\ProductImportPreviewData;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ProductImportStorageService
{
    public function store(
        int $userId,
        string $filename,
        string $fileHash,
        array $rows,
        array $errors
    ): ProductImportPreviewData {
        $token = (string) Str::uuid();
        $expiresAt = now()->addMinutes((int) config('product_import.token_ttl_minutes'));
        $payload = [
            'token' => $token,
            'user_id' => $userId,
            'filename' => $filename,
            'file_hash' => $fileHash,
            'rows' => $rows,
            'errors' => $errors,
            'state' => 'pending',
            'expires_at' => $expiresAt->timestamp,
        ];

        Cache::put($this->key($token), $payload, $expiresAt);

        return $this->toData($payload);
    }

    public function get(string $token, int $userId): ?ProductImportPreviewData
    {
        $payload = Cache::get($this->key($token));
        if (! is_array($payload) || (int) ($payload['user_id'] ?? 0) !== $userId) {
            return null;
        }

        return $this->toData($payload);
    }

    public function markUsed(string $token, int $userId): bool
    {
        $payload = Cache::get($this->key($token));
        if (! is_array($payload)
            || (int) ($payload['user_id'] ?? 0) !== $userId
            || ($payload['state'] ?? null) !== 'pending') {
            return false;
        }

        $payload['state'] = 'used';
        $seconds = max(1, ((int) ($payload['expires_at'] ?? now()->timestamp)) - now()->timestamp);
        Cache::put($this->key($token), $payload, now()->addSeconds($seconds));

        return true;
    }

    public function delete(string $token, int $userId): bool
    {
        if ($this->get($token, $userId) === null) {
            return false;
        }

        Cache::forget($this->key($token));

        return true;
    }

    public function key(string $token): string
    {
        return 'product_import.preview.'.$token;
    }

    private function toData(array $payload): ProductImportPreviewData
    {
        return new ProductImportPreviewData(
            token: (string) $payload['token'],
            userId: (int) $payload['user_id'],
            filename: (string) $payload['filename'],
            fileHash: (string) $payload['file_hash'],
            rows: $payload['rows'] ?? [],
            errors: $payload['errors'] ?? [],
            state: (string) ($payload['state'] ?? 'pending'),
        );
    }
}
