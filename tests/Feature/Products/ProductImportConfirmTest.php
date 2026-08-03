<?php

namespace Tests\Feature\Products;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductUnit;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Services\Products\ProductImportService;
use App\Services\Products\ProductImportStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class ProductImportConfirmTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirm_creates_product_base_unit_default_barcode_and_opening_stock_movement(): void
    {
        $category = $this->category();
        $unit = $this->unit();
        $token = $this->storePreview($category, $unit, 10);

        $result = app(ProductImportService::class)->confirm($token, 7);
        $product = Product::query()->sole();
        $movement = StockMovement::query()->sole();

        $this->assertSame(1, $result->productCount);
        $this->assertSame(1, $result->stockMovementCount);
        $this->assertSame('HAR-0001', $product->product_code);
        $this->assertSame('2000100000014', $product->barcode);
        $this->assertSame('15.00', $product->selling_price);
        $this->assertTrue((bool) $product->price_lock);
        $this->assertSame('10.0000', $product->stock_qty);
        $this->assertSame(1, ProductUnit::query()->where('product_id', $product->id)->count());
        $this->assertSame(1, ProductBarcode::query()->where('product_id', $product->id)->count());
        $this->assertSame('10.0000', $movement->qty);
        $this->assertSame('0.0000', $movement->stock_before);
        $this->assertSame('10.0000', $movement->stock_after);
        $this->assertSame('product_import', $movement->reference_type);
    }

    public function test_confirming_used_token_again_does_not_create_duplicate_products(): void
    {
        $category = $this->category();
        $unit = $this->unit();
        $token = $this->storePreview($category, $unit, 0);

        app(ProductImportService::class)->confirm($token, 7);

        try {
            app(ProductImportService::class)->confirm($token, 7);
            $this->fail('A used import token must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('import', $exception->errors());
        }

        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_valid_manual_code_and_barcode_are_preserved(): void
    {
        $category = $this->category();
        $unit = $this->unit();
        $row = $this->row('สินค้ารหัสเอง', $category, $unit, 0);
        $row['values']['product_code'] = 'HAR-0999';
        $row['values']['barcode'] = '8851234567890';
        $token = app(ProductImportStorageService::class)->store(7, 'products.xlsx', 'hash', [$row], [])->token;

        app(ProductImportService::class)->confirm($token, 7);

        $this->assertDatabaseHas('products', [
            'product_code' => 'HAR-0999',
            'barcode' => '8851234567890',
        ]);
        $this->assertDatabaseHas('product_barcodes', ['barcode' => '8851234567890']);
    }

    public function test_stale_preview_conflict_is_rejected_without_importing_and_token_stays_pending(): void
    {
        $category = $this->category();
        $unit = $this->unit();
        $token = $this->storePreview($category, $unit, 0);

        Product::query()->create([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'name' => 'เหล็กเส้น',
            'cost_price' => '10.00',
            'selling_price' => '15.00',
            'stock_qty' => '0.0000',
            'minimum_stock' => '0.0000',
            'active' => true,
            'product_code' => 'HAR-0999',
            'barcode' => '8851234567890',
        ]);

        try {
            app(ProductImportService::class)->confirm($token, 7);
            $this->fail('A stale preview conflict must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('import', $exception->errors());
        }

        $this->assertDatabaseCount('products', 1);
        $this->assertSame('pending', app(ProductImportStorageService::class)->get($token, 7)->state);
    }

    public function test_failure_in_the_middle_rolls_back_all_products_units_barcodes_and_movements(): void
    {
        $category = $this->category();
        $unit = $this->unit();
        $token = app(ProductImportStorageService::class)->store(7, 'products.xlsx', 'hash', [
            $this->row('สินค้า A', $category, $unit, 5),
            $this->row('สินค้า B', $category, $unit, 7),
        ], [])->token;
        $created = 0;
        Product::creating(function () use (&$created): void {
            $created++;
            if ($created === 2) {
                throw new RuntimeException('synthetic import failure');
            }
        });

        try {
            app(ProductImportService::class)->confirm($token, 7);
            $this->fail('The synthetic failure should be thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('synthetic import failure', $exception->getMessage());
        }

        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_units', 0);
        $this->assertDatabaseCount('product_barcodes', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame('pending', app(ProductImportStorageService::class)->get($token, 7)->state);
    }

    private function storePreview(Category $category, Unit $unit, int $openingStock): string
    {
        return app(ProductImportStorageService::class)->store(7, 'products.xlsx', 'hash', [$this->row('เหล็กเส้น', $category, $unit, $openingStock)], [])->token;
    }

    private function row(string $name, Category $category, Unit $unit, int $openingStock): array
    {
        return [
            'row_number' => 2,
            'values' => [
                'product_name' => $name,
                'category' => $category->name,
                'category_id' => $category->id,
                'base_unit' => $unit->name,
                'unit_id' => $unit->id,
                'cost_price' => '10.00',
                'selling_price' => '15.00',
                'opening_stock' => number_format($openingStock, 4, '.', ''),
                'product_code' => null,
                'barcode' => null,
                'status' => true,
                'price_locked' => true,
                'description' => 'รายละเอียด',
            ],
            'original_values' => [],
            'errors' => [],
        ];
    }

    private function category(): Category
    {
        return Category::query()->create([
            'name' => 'Hardware',
            'code_prefix' => 'HAR',
            'barcode_prefix' => '001',
            'active' => true,
        ]);
    }

    private function unit(): Unit
    {
        return Unit::query()->create([
            'code' => 'KG',
            'name' => 'กิโลกรัม',
            'short_name' => 'กก.',
            'active' => true,
        ]);
    }
}
