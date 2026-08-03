<?php

namespace Tests\Feature\Sales;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleV3AssetRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_pos_v3_renders_a_dom_mount_with_versioned_cart_assets(): void
    {
        $owner = User::factory()->create([
            'name' => 'TEST-POS-V3-ASSET-OWNER',
            'role' => 'owner',
        ]);

        $response = $this->actingAs($owner)->get(route('sales.v3'));

        $response->assertOk();

        $dom = new \DOMDocument;
        $previousLibxmlState = libxml_use_internal_errors(true);

        try {
            $loaded = $dom->loadHTML($response->getContent());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousLibxmlState);
        }

        $this->assertTrue($loaded);

        $xpath = new \DOMXPath($dom);
        $cartMounts = $xpath->query("//*[@id='v3-cart-items']");
        $this->assertNotFalse($cartMounts);
        $this->assertCount(1, $cartMounts);
        $this->assertSame(
            'pos-v3-cart-items',
            $cartMounts->item(0)->getAttribute('class')
        );

        $saleScripts = [];

        foreach ($dom->getElementsByTagName('script') as $script) {
            $src = $script->getAttribute('src');

            if (str_contains($src, '/js/modules/sale-v3.js')) {
                $saleScripts[] = $src;
            }
        }

        $this->assertCount(1, $saleScripts);
        $this->assertStringContainsString('?v=', $saleScripts[0]);

        $saleStyles = [];

        foreach ($dom->getElementsByTagName('link') as $link) {
            if ($link->getAttribute('rel') !== 'stylesheet') {
                continue;
            }

            $href = $link->getAttribute('href');

            if (str_contains($href, '/css/sale-v3.css')) {
                $saleStyles[] = $href;
            }
        }

        $this->assertCount(1, $saleStyles);
        $this->assertStringContainsString('?v=', $saleStyles[0]);
    }
}
