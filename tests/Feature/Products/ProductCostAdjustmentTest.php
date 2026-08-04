<?php

namespace Tests\Feature\Products;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductPriceHistory;
use App\Models\ProductUnit;
use App\Models\Unit;
use App\Models\User;
use App\Services\Pricing\PricingService;
use App\Services\Products\ProductCostAdjustmentService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductCostAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_manager_can_change_cost_without_changing_selling_price_stock_or_price_lock(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $product = $this->product([
            'cost_price' => '112.00',
            'selling_price' => '150.00',
            'stock_qty' => '7.0000',
            'pricing_reviewed_cost' => '112.00',
            'price_lock' => true,
        ]);
        $unit = Unit::query()->create([
            'code' => 'BOX',
            'name' => 'กล่อง',
            'short_name' => 'กล่อง',
            'active' => true,
        ]);
        $productUnit = ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'conversion_rate' => '10.0000',
            'conversion_confirmed_at' => now(),
            'is_base_unit' => false,
            'is_purchase_unit' => true,
            'is_sale_unit' => true,
            'purchase_price' => '112.00',
            'selling_price' => '150.00',
            'active' => true,
        ]);
        $barcode = ProductBarcode::query()->create([
            'product_id' => $product->id,
            'product_unit_id' => $productUnit->id,
            'barcode' => '8850000000011',
            'is_default' => true,
            'active' => true,
        ]);

        $this->actingAs($manager)
            ->put(route('products.cost.update', $product), [
                'current_cost_price' => '112.00',
                'cost_price' => '120.25',
                'reason' => 'ปรับตามใบซื้อรอบล่าสุด',
            ])
            ->assertRedirect(route('products.index'))
            ->assertSessionHas('success');

        $saved = $product->fresh();

        $this->assertSame('120.25', $saved->cost_price);
        $this->assertSame('150.00', $saved->selling_price);
        $this->assertSame('7.0000', $saved->stock_qty);
        $this->assertTrue((bool) $saved->price_lock);
        $this->assertSame('112.00', number_format((float) $saved->pricing_reviewed_cost, 2, '.', ''));
        $this->assertDatabaseHas('product_units', [
            'id' => $productUnit->id,
            'conversion_rate' => '10.0000',
            'purchase_price' => '112.00',
            'selling_price' => '150.00',
        ]);
        $this->assertDatabaseHas('product_barcodes', [
            'id' => $barcode->id,
            'barcode' => '8850000000011',
        ]);

        $history = ProductPriceHistory::query()
            ->where('product_id', $product->id)
            ->where('created_from', 'manual_cost_adjustment')
            ->sole();

        $this->assertSame($manager->id, $history->user_id);
        $this->assertSame('112.00', $history->old_cost_price);
        $this->assertSame('120.25', $history->new_cost_price);
        $this->assertSame('150.00', $history->old_selling_price);
        $this->assertSame('150.00', $history->new_selling_price);
        $this->assertSame('29.75', $history->profit_amount);
        $this->assertSame('ปรับตามใบซื้อรอบล่าสุด', $history->remark);
        $this->assertSame('pending_review', app(PricingService::class)->calculate($saved)['status']);
    }

    public function test_same_cost_is_a_successful_no_op_without_creating_history(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $product = $this->product(['cost_price' => '112.00']);

        $this->actingAs($manager)
            ->put(route('products.cost.update', $product), [
                'current_cost_price' => '112.00',
                'cost_price' => '112.00',
                'reason' => 'ตรวจสอบต้นทุนแล้ว',
            ])
            ->assertRedirect(route('products.index'))
            ->assertSessionHas('success');

        $this->assertSame(0, ProductPriceHistory::query()->count());
        $this->assertSame('112.00', $product->fresh()->cost_price);
    }

    public function test_unreviewed_product_keeps_old_cost_as_the_pending_review_marker(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $product = $this->product([
            'cost_price' => '112.00',
            'selling_price' => '150.00',
            'pricing_reviewed_cost' => null,
        ]);

        $this->actingAs($manager)
            ->put(route('products.cost.update', $product), [
                'current_cost_price' => '112.00',
                'cost_price' => '120.25',
                'reason' => 'ตั้ง marker ก่อนส่งตรวจราคา',
            ])
            ->assertRedirect(route('products.index'));

        $saved = $product->fresh();

        $this->assertSame('112.00', number_format((float) $saved->pricing_reviewed_cost, 2, '.', ''));
        $this->assertSame('pending_review', app(PricingService::class)->calculate($saved)['status']);
    }

    public function test_cost_change_requires_a_valid_money_snapshot_and_reason(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $product = $this->product(['cost_price' => '112.00']);

        $this->from(route('products.index'))
            ->actingAs($manager)
            ->put(route('products.cost.update', $product), [
                'current_cost_price' => '112.000',
                'cost_price' => '-1',
                'reason' => '  ',
            ])
            ->assertRedirect(route('products.index'))
            ->assertSessionHasErrors(['current_cost_price', 'cost_price', 'reason']);

        $this->assertSame('112.00', $product->fresh()->cost_price);
        $this->assertSame(0, ProductPriceHistory::query()->count());
    }

    public function test_stale_cost_snapshot_is_rejected_without_a_history_row(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $product = $this->product(['cost_price' => '112.00']);
        $product->update(['cost_price' => '113.00']);

        $this->actingAs($manager)
            ->put(route('products.cost.update', $product), [
                'current_cost_price' => '112.00',
                'cost_price' => '120.25',
                'reason' => 'ปรับต้นทุนจากหน้ารายการสินค้า',
            ])
            ->assertRedirect(route('products.index'))
            ->assertSessionHas('error');

        $this->assertSame('113.00', $product->fresh()->cost_price);
        $this->assertSame(0, ProductPriceHistory::query()->count());
    }

    public function test_history_failure_rolls_back_the_cost_update(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('The rollback trigger uses SQLite test syntax only.');
        }

        $manager = User::factory()->create(['role' => 'manager']);
        $product = $this->product([
            'cost_price' => '112.00',
            'selling_price' => '150.00',
        ]);
        $trigger = 'product_cost_adjustment_history_failure';

        DB::statement("CREATE TRIGGER {$trigger} BEFORE INSERT ON product_price_histories BEGIN SELECT RAISE(ABORT, 'test history failure'); END");

        try {
            app(ProductCostAdjustmentService::class)->adjust(
                $product,
                '120.25',
                '112.00',
                'ทดสอบ rollback',
                $manager->id
            );

            $this->fail('Expected the history insert to fail.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('test history failure', $exception->getMessage());
        } finally {
            DB::statement("DROP TRIGGER IF EXISTS {$trigger}");
        }

        $this->assertSame('112.00', $product->fresh()->cost_price);
        $this->assertSame(0, ProductPriceHistory::query()->count());
    }

    public function test_cashier_cannot_change_product_cost(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        $product = $this->product();

        $this->actingAs($cashier)
            ->put(route('products.cost.update', $product), [
                'current_cost_price' => '10.00',
                'cost_price' => '12.00',
                'reason' => 'ไม่มีสิทธิ์ปรับต้นทุน',
            ])
            ->assertForbidden();

        $this->assertSame('10.00', $product->fresh()->cost_price);
        $this->assertSame(0, ProductPriceHistory::query()->count());
    }

    public function test_product_page_exposes_a_separate_cost_adjustment_modal(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $this->product();

        $this->actingAs($manager)
            ->get(route('products.index'))
            ->assertOk()
            ->assertSee('data-open-cost-modal', false)
            ->assertSee('id="productCostModal"', false)
            ->assertSee('name="current_cost_price"', false)
            ->assertSee('name="cost_price"', false)
            ->assertSee('name="reason"', false)
            ->assertSee('ราคาขายจะไม่เปลี่ยน', false)
            ->assertSee('pending_review', false);
    }

    private function product(array $overrides = []): Product
    {
        $category = Category::query()->create([
            'name' => 'Cost Test Category '.Str::random(6),
            'code_prefix' => 'CST',
            'barcode_prefix' => str_pad((string) (Category::query()->count() + 1), 3, '0', STR_PAD_LEFT),
            'active' => true,
        ]);

        return Product::query()->create(array_merge([
            'category_id' => $category->id,
            'name' => 'Cost Test Product',
            'product_code' => 'COST-'.Str::upper(Str::random(6)),
            'cost_price' => '10.00',
            'selling_price' => '15.00',
            'stock_qty' => '0.0000',
            'minimum_stock' => '0.0000',
            'pricing_reviewed_cost' => null,
            'price_lock' => false,
            'active' => true,
        ], $overrides));
    }
}
