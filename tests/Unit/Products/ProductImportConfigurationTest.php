<?php

namespace Tests\Unit\Products;

use Tests\TestCase;

class ProductImportConfigurationTest extends TestCase
{
    public function test_product_import_configuration_exposes_safe_file_and_token_limits(): void
    {
        $this->assertSame(500, config('product_import.max_rows'));
        $this->assertSame(5120, config('product_import.max_file_size_kb'));
        $this->assertSame(30, config('product_import.token_ttl_minutes'));
        $this->assertSame(['xlsx'], config('product_import.allowed_extensions'));
    }
}
