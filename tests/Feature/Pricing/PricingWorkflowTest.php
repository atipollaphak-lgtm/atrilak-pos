<?php

namespace Tests\Feature\Pricing;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Services\Pricing\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_changed_average_cost_is_pending_until_one_product_is_reviewed(): void
    {
        $product = Product::query()->create([
            'category_id' => Category::query()->create(['name' => 'Pricing test'])->id,
            'name' => 'Pending price',
            'cost_price' => '5.80',
            'selling_price' => '7.00',
            'pricing_reviewed_cost' => '5.20',
            'pricing_method' => 'percentage',
            'pricing_value' => '30',
            'rounding_direction' => 'up',
            'rounding_unit' => '1',
            'active' => true,
        ]);

        $service = app(PricingService::class);
        $preview = $service->calculate($product);

        $this->assertSame('pending_review', $preview['status']);
        $this->assertSame('8.00', $preview['suggested_price']);

        $saved = $service->review($product, [
            'pricing_method' => 'percentage',
            'pricing_value' => '30',
            'rounding_direction' => 'up',
            'rounding_unit' => '1',
        ]);

        $this->assertSame('normal', $saved['status']);
        $this->assertSame('8.00', $product->fresh()->selling_price);
        $this->assertSame(1, ProductPriceHistory::query()->where('created_from', 'pricing_review')->count());
    }
}
