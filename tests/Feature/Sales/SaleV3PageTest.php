<?php

namespace Tests\Feature\Sales;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleV3PageTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_can_open_v3_without_changing_v2_route(): void
    {
        Customer::query()->create([
            'code' => 'CUS-TEST-TAX',
            'name' => 'TEST Tax Customer',
            'tax_number' => '0100000000001',
            'active' => true,
        ]);

        $response = $this->actingAs(User::factory()->create(['role' => 'cashier']))
            ->get('/sales-v3');

        $response->assertOk()
            ->assertSee('id="pos-v3"', false)
            ->assertSee('sale-v3.css', false)
            ->assertSee('sale-v3.js', false)
            ->assertSee('v3-product-search', false)
            ->assertSee('data-document-url-template=', false)
            ->assertSee('id="v3-hold-bill"', false)
            ->assertSee('id="v3-delivery"', false)
            ->assertSee('id="v3-pickup-button"', false)
            ->assertSee('aria-pressed="false"', false)
            ->assertSee('aria-pressed="true"', false)
            ->assertSee('data-backdrop="static"', false)
            ->assertSee('data-keyboard="false"', false)
            ->assertSee('data-tax-number="0100000000001"', false)
            ->assertSee('/sales-v3/customers', false)
            ->assertSee('v3-open-customer-create', false)
            ->assertSee('v3-new-customer-branch-number', false)
            ->assertSee('v3-price-zone-select', false);

        $this->get('/sales-v2')->assertOk();
    }

    public function test_guest_cannot_open_v3(): void
    {
        $this->get('/sales-v3')->assertRedirect();
    }

    public function test_pos_v3_renders_compact_accessible_customer_and_product_controls(): void
    {
        $html = $this->actingAs(User::factory()->create(['role' => 'cashier']))
            ->get(route('sales.v3'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-customer-show-url-template=', $html);
        $this->assertStringContainsString('id="v3-customer-summary"', $html);
        $this->assertStringContainsString('id="v3-price-zone-select"', $html);
        $this->assertMatchesRegularExpression('/id="v3-price-zone-select"[^>]*disabled/', $html);

        foreach (['ดูข้อมูลลูกค้า', 'ค้นหาลูกค้า', 'เพิ่มลูกค้า', 'ล้างลูกค้า'] as $label) {
            $this->assertMatchesRegularExpression(
                '/aria-label="'.preg_quote($label, '/').'"[^>]*title="'.preg_quote($label, '/').'"|title="'.preg_quote($label, '/').'"[^>]*aria-label="'.preg_quote($label, '/').'"/',
                $html
            );
        }

        $this->assertStringNotContainsString('<span><i class="fas fa-boxes mr-2"></i>เลือกสินค้า</span>', $html);
    }

    public function test_product_cards_render_stock_first_with_low_and_out_states_and_no_baht_suffix(): void
    {
        $category = Category::query()->create([
            'name' => 'POS V3 card category',
            'active' => true,
        ]);
        $longName = 'สินค้าทดสอบชื่อยาวมากสำหรับตรวจการตัดข้อความหนึ่งบรรทัด';
        Product::query()->create([
            'category_id' => $category->id,
            'name' => $longName,
            'unit' => 'ถุง',
            'cost_price' => '100.00',
            'selling_price' => '123.00',
            'stock_qty' => '1.0000',
            'minimum_stock' => '2.0000',
            'active' => true,
        ]);
        Product::query()->create([
            'category_id' => $category->id,
            'name' => 'สินค้าหมดสำหรับทดสอบ',
            'unit' => 'ก้อน',
            'cost_price' => '10.00',
            'selling_price' => '20.00',
            'stock_qty' => '0.0000',
            'minimum_stock' => '1.0000',
            'active' => true,
        ]);

        $html = $this->actingAs(User::factory()->create(['role' => 'cashier']))
            ->get(route('sales.v3'))
            ->assertOk()
            ->getContent();
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        $xpath = new DOMXPath($document);
        $cards = $xpath->query('//button[contains(concat(" ", normalize-space(@class), " "), " v3-product-card ")]');

        $this->assertNotFalse($cards);
        $this->assertCount(2, $cards);
        $lowCard = $cards->item(0);
        $outCard = $cards->item(1);
        $this->assertStringContainsString('is-low', $lowCard->getAttribute('class'));
        $this->assertStringContainsString('is-out', $outCard->getAttribute('class'));

        $lowChildren = array_values(array_filter(
            iterator_to_array($lowCard->childNodes),
            fn ($node): bool => $node instanceof \DOMElement
        ));
        $this->assertSame(
            ['v3-product-stock', 'v3-product-image', 'v3-product-name', 'v3-product-price'],
            array_map(fn (\DOMElement $node): string => $node->getAttribute('class'), $lowChildren)
        );
        $this->assertSame('เหลือ 1.00 ถุง', trim($lowChildren[0]->textContent));
        $this->assertSame($longName, $lowChildren[2]->getAttribute('title'));
        $this->assertSame('123.00', trim($lowChildren[3]->textContent));
        $this->assertStringNotContainsString('บาท', $lowCard->textContent);
    }

    public function test_pos_v3_uses_the_public_disk_url_for_product_images(): void
    {
        $category = Category::query()->create([
            'name' => 'POS V3 Images',
            'code_prefix' => 'PVI',
            'barcode_prefix' => '009',
            'active' => true,
        ]);

        Product::query()->create([
            'category_id' => $category->id,
            'name' => 'POS V3 Image Product',
            'product_code' => 'POS-V3-IMAGE',
            'cost_price' => '10.00',
            'selling_price' => '15.00',
            'stock_qty' => '2.0000',
            'minimum_stock' => '0.0000',
            'active' => true,
            'image_path' => 'products/v3-image.jpg',
        ]);

        $this->actingAs(User::factory()->create(['role' => 'cashier']))
            ->get('/sales-v3')
            ->assertOk()
            ->assertSee('http://localhost/storage/products/v3-image.jpg', false)
            ->assertDontSee('src="http://localhost/products/v3-image.jpg"', false);
    }
}
