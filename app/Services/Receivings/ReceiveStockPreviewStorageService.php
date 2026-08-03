<?php

namespace App\Services\Receivings;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

class ReceiveStockPreviewStorageService
{
    private const TTL_SECONDS = 600;

    public function put(int $userId, array $payload): string
    {
        $token = Str::random(64);
        Cache::put($this->key($token), [
            'user_id' => $userId,
            'payload' => $payload,
            'payload_hash' => hash('sha256', serialize($payload)),
        ], self::TTL_SECONDS);

        return $token;
    }

    public function get(string $token, int $userId): array
    {
        $preview = Cache::get($this->key($token));

        if (! is_array($preview) || (int) ($preview['user_id'] ?? 0) !== $userId) {
            throw new RuntimeException('Preview หมดอายุหรือไม่ตรงกับผู้ใช้');
        }

        return $preview['payload'];
    }

    public function forget(string $token): void
    {
        Cache::forget($this->key($token));
    }

    private function key(string $token): string
    {
        return 'receivings:preview:'.$token;
    }
}
