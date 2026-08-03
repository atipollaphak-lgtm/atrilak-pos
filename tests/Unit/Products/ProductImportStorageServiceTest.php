<?php

namespace Tests\Unit\Products;

use App\Services\Products\ProductImportStorageService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ProductImportStorageServiceTest extends TestCase
{
    public function test_preview_payload_is_owned_by_user_and_can_be_marked_used_once(): void
    {
        Cache::flush();
        $service = app(ProductImportStorageService::class);

        $preview = $service->store(7, 'products.xlsx', 'hash', [
            ['row_number' => 2, 'values' => [], 'original_values' => [], 'errors' => []],
        ], []);

        $this->assertSame($preview->token, $service->get($preview->token, 7)->token);
        $this->assertNull($service->get($preview->token, 8));
        $this->assertTrue($service->markUsed($preview->token, 7));
        $this->assertFalse($service->markUsed($preview->token, 7));
        $this->assertSame('used', $service->get($preview->token, 7)->state);
    }
}
