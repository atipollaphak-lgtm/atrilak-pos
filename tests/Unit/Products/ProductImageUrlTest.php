<?php

namespace Tests\Unit\Products;

use App\Models\Product;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ProductImageUrlTest extends TestCase
{
    public function test_public_disk_paths_are_normalized_to_one_storage_url(): void
    {
        Config::set('filesystems.disks.public.url', 'http://localhost/storage');

        $expected = 'http://localhost/storage/products/test.jpg';

        $this->assertSame($expected, Product::make(['image_path' => 'products/test.jpg'])->image_url);
        $this->assertSame($expected, Product::make(['image_path' => 'storage/products/test.jpg'])->image_url);
        $this->assertSame($expected, Product::make(['image_path' => '/storage/products/test.jpg'])->image_url);
        $this->assertSame($expected, Product::make(['image_path' => 'storage\\products\\test.jpg'])->image_url);
    }

    public function test_full_urls_are_preserved_and_filesystem_paths_are_rejected(): void
    {
        $this->assertSame(
            'http://cdn.example.test/products/test.jpg',
            Product::make(['image_path' => 'http://cdn.example.test/products/test.jpg'])->image_url
        );
        $this->assertSame(
            'https://cdn.example.test/products/test.jpg',
            Product::make(['image_path' => 'https://cdn.example.test/products/test.jpg'])->image_url
        );
        $this->assertNull(Product::make(['image_path' => 'C:\\uploads\\test.jpg'])->image_url);
        $this->assertNull(Product::make(['image_path' => null])->image_url);
        $this->assertNull(Product::make(['image_path' => 'javascript:alert(1)'])->image_url);
    }
}
