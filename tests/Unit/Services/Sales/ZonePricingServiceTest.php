<?php

namespace Tests\Unit\Services\Sales;

use App\Models\DeliveryZone;
use App\Models\Product;
use App\Models\ProductPriceTier;
use App\Models\ProductUnit;
use App\Services\Sales\ZonePricingService;
use Tests\TestCase;

class ZonePricingServiceTest extends TestCase
{
    public function test_zone_markup_is_applied_after_tier_price_and_rounded_with_decimal_arithmetic(): void
    {
        $product = new Product([
            'selling_price' => '100.00',
            'rounding_direction' => 'nearest',
            'rounding_unit' => '0.50',
        ]);
        $unit = new ProductUnit(['selling_price' => '100.00']);
        $unit->setRelation('priceTiers', collect([
            new ProductPriceTier([
                'min_qty' => '10',
                'fixed_price' => '120.00',
                'discount_percent' => null,
            ]),
        ]));
        $zone = new DeliveryZone([
            'price_markup_percent' => '3.00',
            'minimum_profit' => '300.00',
            'active' => true,
        ]);

        $result = app(ZonePricingService::class)->priceLine(
            ['qty' => '10.00'],
            $product,
            $unit,
            $zone,
            false
        );

        $this->assertSame('120.00', $result['base_unit_price']);
        $this->assertSame('123.60000000', $result['zone_unit_price_before_rounding']);
        $this->assertSame('123.50', $result['zone_unit_price']);
    }

    public function test_dynamic_delivery_fee_is_the_non_negative_profit_shortfall(): void
    {
        $service = app(ZonePricingService::class);
        $zone = new DeliveryZone([
            'price_markup_percent' => '3.00',
            'minimum_profit' => '300.00',
            'active' => true,
        ]);

        $this->assertSame('80.00', $service->deliveryFee('220.00', $zone, false));
        $this->assertSame('10.00', $service->deliveryFee('290.00', $zone, false));
        $this->assertSame('0.00', $service->deliveryFee('300.00', $zone, false));
        $this->assertSame('0.00', $service->deliveryFee('380.00', $zone, false));
        $this->assertSame('0.00', $service->deliveryFee('0.00', $zone, true));
    }
}
