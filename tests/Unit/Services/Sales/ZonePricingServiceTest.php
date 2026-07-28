<?php

namespace Tests\Unit\Services\Sales;

use App\Models\DeliveryZone;
use App\Models\Product;
use App\Models\ProductPriceTier;
use App\Models\ProductUnit;
use App\Services\Sales\ZonePricingService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ZonePricingServiceTest extends TestCase
{
    #[DataProvider('zoneRoundingIncrementProvider')]
    public function test_delivery_uses_zone_ceiling_increment(string $increment, string $expected): void
    {
        $product = new Product([
            'selling_price' => '6.30',
            'rounding_direction' => 'up',
            'rounding_unit' => '0.01',
        ]);
        $zone = new DeliveryZone([
            'price_markup_percent' => '3.00',
            'rounding_increment' => $increment,
            'minimum_profit' => '300.00',
            'active' => true,
        ]);

        $result = app(ZonePricingService::class)->priceLine(
            ['qty' => '1.00'],
            $product,
            null,
            $zone,
            false
        );

        $this->assertSame('6.48900000', $result['zone_unit_price_before_rounding']);
        $this->assertSame($expected, $result['zone_unit_price']);
        $this->assertSame($increment, $result['rounding_increment']);
    }

    public static function zoneRoundingIncrementProvider(): array
    {
        return [
            'quarter baht' => ['0.25', '6.50'],
            'half baht' => ['0.50', '6.50'],
            'one baht' => ['1.00', '7.00'],
            'five baht' => ['5.00', '10.00'],
            'ten baht' => ['10.00', '10.00'],
        ];
    }

    public function test_pickup_does_not_apply_zone_markup_or_zone_rounding(): void
    {
        $product = new Product([
            'selling_price' => '6.30',
            'rounding_direction' => 'up',
            'rounding_unit' => '0.01',
        ]);
        $zone = new DeliveryZone([
            'price_markup_percent' => '3.00',
            'rounding_increment' => '10.00',
            'minimum_profit' => '300.00',
            'active' => true,
        ]);

        $result = app(ZonePricingService::class)->priceLine(
            ['qty' => '1.00'],
            $product,
            null,
            $zone,
            true
        );

        $this->assertSame('0.00', $result['zone_markup_percent']);
        $this->assertSame('6.30', $result['zone_unit_price']);
        $this->assertSame('0.00', $result['rounding_increment']);
    }

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
            'rounding_increment' => '0.50',
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
        $this->assertSame('124.00', $result['zone_unit_price']);
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
