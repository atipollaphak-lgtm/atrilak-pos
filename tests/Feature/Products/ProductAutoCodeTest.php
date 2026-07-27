<?php

namespace Tests\Feature\Products;

use App\Http\Middleware\RoleMiddleware;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductAutoCodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            Authenticate::class,
            RoleMiddleware::class,
            ValidateCsrfToken::class,
        ]);
    }

    public function test_new_product_receives_server_generated_code_and_ean13_barcode(): void
    {
        $category = $this->category('Cement', 'CEM', '001');

        $this->post(route('products.store'), $this->payload($category, [
            'product_code' => 'CLIENT-CODE',
            'barcode' => '1234567890123',
        ]))->assertRedirect();

        $product = Product::query()->where('name', 'Test Product')->firstOrFail();

        $this->assertSame('CEM-0001', $product->product_code);
        $this->assertSame('2000100000014', $product->barcode);
        $this->assertMatchesRegularExpression('/^\d{13}$/', $product->barcode);
        $this->assertEan13CheckDigit($product->barcode);
    }

    public function test_sequence_is_shared_by_code_and_barcode_per_category(): void
    {
        $cement = $this->category('Cement', 'CEM', '001');
        $block = $this->category('Block', 'BLK', '002');

        $this->post(route('products.store'), $this->payload($cement, ['name' => 'Cement 1']))
            ->assertRedirect();
        $this->post(route('products.store'), $this->payload($cement, ['name' => 'Cement 2']))
            ->assertRedirect();
        $this->post(route('products.store'), $this->payload($block, ['name' => 'Block 1']))
            ->assertRedirect();

        $this->assertSame('CEM-0001', Product::query()->where('name', 'Cement 1')->value('product_code'));
        $this->assertSame('CEM-0002', Product::query()->where('name', 'Cement 2')->value('product_code'));
        $this->assertSame('BLK-0001', Product::query()->where('name', 'Block 1')->value('product_code'));
        $this->assertSame('2000100000021', Product::query()->where('name', 'Cement 2')->value('barcode'));
    }

    public function test_product_creation_requires_both_category_prefixes(): void
    {
        $category = Category::query()->create(['name' => 'Unconfigured', 'active' => true]);

        $this->from(route('products.index'))
            ->post(route('products.store'), $this->payload($category))
            ->assertRedirect(route('products.index'))
            ->assertSessionHasErrors('category_id');

        $this->assertDatabaseCount('products', 0);
    }

    public function test_existing_code_and_barcode_do_not_change_when_product_is_updated_or_recategorized(): void
    {
        $cement = $this->category('Cement', 'CEM', '001');
        $block = $this->category('Block', 'BLK', '002');

        $this->post(route('products.store'), $this->payload($cement))->assertRedirect();
        $product = Product::query()->where('name', 'Test Product')->firstOrFail();

        $this->put(route('products.update', $product), [
            'name' => 'Renamed Product',
            'category_id' => $block->id,
            'product_code' => 'CLIENT-CHANGE',
            'barcode' => '9999999999999',
            'minimum_stock' => '0',
        ])->assertRedirect();

        $product->refresh();
        $this->assertSame('CEM-0001', $product->product_code);
        $this->assertSame('2000100000014', $product->barcode);
        $this->assertSame($block->id, $product->category_id);
    }

    private function category(string $name, string $codePrefix, string $barcodePrefix): Category
    {
        return Category::query()->create([
            'name' => $name,
            'code_prefix' => $codePrefix,
            'barcode_prefix' => $barcodePrefix,
            'active' => true,
        ]);
    }

    private function payload(Category $category, array $overrides = []): array
    {
        return array_merge([
            'category_id' => $category->id,
            'name' => 'Test Product',
            'cost_price' => '10.00',
            'selling_price' => '15.00',
            'stock_qty' => '0',
            'minimum_stock' => '0',
            'vat_enabled' => false,
            'active' => true,
        ], $overrides);
    }

    private function assertEan13CheckDigit(string $barcode): void
    {
        $digits = str_split(substr($barcode, 0, 12));
        $sum = array_sum(array_map(
            fn (string $digit, int $index): int => (int) $digit * ($index % 2 === 0 ? 1 : 3),
            $digits,
            array_keys($digits)
        ));

        $this->assertSame((string) ((10 - ($sum % 10)) % 10), substr($barcode, -1));
    }
}
