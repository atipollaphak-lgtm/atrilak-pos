<?php

namespace Tests\Unit\Customers;

use App\Services\Customers\CustomerImportStorageService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CustomerImportStorageServiceTest extends TestCase
{
    public function test_preview_is_scoped_to_user_and_has_status_counts(): void
    {
        $service = app(CustomerImportStorageService::class);
        $preview = $service->store(
            17,
            'members.xlsx',
            str_repeat('a', 64),
            'C2M',
            [
                ['status' => 'ready'],
                ['status' => 'review_required'],
                ['status' => 'duplicate'],
                ['status' => 'invalid'],
            ],
            [],
        );

        $this->assertNotSame('', $preview->token);
        $this->assertSame('C2M', $preview->sourceSystem);
        $this->assertSame(['ready' => 1, 'review_required' => 1, 'duplicate' => 1, 'invalid' => 1], $preview->counts());
        $this->assertNotNull($service->get($preview->token, 17));
        $this->assertNull($service->get($preview->token, 18));
    }

    public function test_used_preview_cannot_be_used_again_and_wrong_user_cannot_delete_it(): void
    {
        $service = app(CustomerImportStorageService::class);
        $preview = $service->store(17, 'members.xlsx', str_repeat('b', 64), 'C2M', [], []);

        $this->assertTrue($service->markUsed($preview->token, 17));
        $this->assertFalse($service->markUsed($preview->token, 17));
        $this->assertFalse($service->delete($preview->token, 18));
        $this->assertTrue($service->delete($preview->token, 17));
        $this->assertNull($service->get($preview->token, 17));
    }

    public function test_preview_ttl_uses_configured_customer_import_duration(): void
    {
        config(['customer_import.token_ttl_minutes' => 7]);
        $service = app(CustomerImportStorageService::class);
        $preview = $service->store(17, 'members.xlsx', str_repeat('c', 64), 'C2M', [], []);

        $payload = Cache::get($service->key($preview->token));

        $this->assertIsArray($payload);
        $this->assertGreaterThanOrEqual(now()->addMinutes(6)->timestamp, $payload['expires_at']);
        $this->assertLessThanOrEqual(now()->addMinutes(8)->timestamp, $payload['expires_at']);
    }
}
