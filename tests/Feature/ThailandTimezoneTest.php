<?php

namespace Tests\Feature;

use App\Http\Middleware\RoleMiddleware;
use App\Models\Category;
use App\Models\DailyPaymentClosing;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThailandTimezoneTest extends TestCase
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

        Carbon::setTestNow(Carbon::parse('2026-07-21 17:30:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_default_sale_and_daily_closing_dates_use_the_bangkok_calendar_date(): void
    {
        $this->assertSame('Asia/Bangkok', config('app.timezone'));
        $this->assertSame('2026-07-22', now()->toDateString());

        $product = $this->product();

        $this->postJson(route('sales.v2.store'), [
            'idempotency_key' => '11111111-1111-4111-8111-111111111111',
            'delivery_type' => 'pickup',
            'discount' => '0.00',
            'delivery_fee' => '0.00',
            'payment_method' => 'cash',
            'cash_amount' => '100.00',
            'promptpay_amount' => '0.00',
            'received_amount' => '100.00',
            'items' => [[
                'product_id' => $product->id,
                'product_unit_id' => null,
                'qty' => '1.00',
                'selling_price' => '100.00',
            ]],
        ])->assertOk();

        $this->assertSame('2026-07-22', (string) Sale::query()->sole()->sale_date);

        $this->actingAs(User::factory()->create(['role' => 'manager']))
            ->get(route('daily-payment-closings.create'))
            ->assertRedirect();

        $this->assertSame('2026-07-22', (string) DailyPaymentClosing::query()->sole()->business_date);
    }

    private function product(): Product
    {
        $category = Category::query()->create(['name' => 'Timezone category']);

        return Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Timezone product',
            'cost_price' => '50.00',
            'selling_price' => '100.00',
            'stock_qty' => '10.0000',
            'minimum_stock' => '0.0000',
            'active' => true,
        ]);
    }
}
