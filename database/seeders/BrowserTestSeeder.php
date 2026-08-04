<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerDeliveryAddress;
use App\Models\DeliveryZone;
use App\Models\HoldBill;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductUnit;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\User;
use App\Support\DatabaseEnvironmentGuard;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\Permission\Models\Role;

class BrowserTestSeeder extends Seeder
{
    private const TEST_PASSWORD = 'BTEST-browser-only-password';

    private const REAL_IMAGE_PATH = 'products/browser-test-real-image.svg';

    public function run(): void
    {
        DatabaseEnvironmentGuard::assertTestDatabase(
            app()->environment(),
            (string) DB::connection()->getDatabaseName()
        );

        $this->writeRealImageFixture();

        DB::transaction(function (): void {
            $roles = $this->seedRoles();
            $users = $this->seedUsers($roles);
            $setting = $this->seedSetting();
            $category = Category::updateOrCreate(
                ['name' => 'BTEST Browser Fixture Category'],
                [
                    'description' => 'Local-only Browser and Print verification fixture.',
                    'code_prefix' => 'BT',
                    'barcode_prefix' => 'BT',
                    'active' => true,
                ]
            );
            $unit = Unit::updateOrCreate(
                ['code' => 'BTEST'],
                [
                    'name' => 'piece',
                    'short_name' => 'pc',
                    'active' => true,
                    'sort_order' => 900,
                ]
            );
            $zone = DeliveryZone::updateOrCreate(
                ['name' => 'BTEST Delivery Zone'],
                [
                    'price_markup_percent' => 0,
                    'rounding_increment' => 1,
                    'sort_order' => 900,
                    'base_delivery_fee' => 150,
                    'free_delivery_min_amount' => 50000,
                    'minimum_profit' => 0,
                    'active' => true,
                    'remark' => 'Local-only Browser and Print verification fixture.',
                ]
            );

            $products = $this->seedProducts($category, $unit);
            [$customer, $address] = $this->seedCustomer($zone);

            $this->seedPrintSales($products['print'], $customer, $address, $zone, $setting);
            $this->seedHoldBill($products['cost'], $users['cashier'], $customer, $address, $zone);
        });

        $this->command?->info('Browser test fixtures seeded in the approved Test DB.');
    }

    /** @return array<string, Role> */
    private function seedRoles(): array
    {
        $roles = [];

        foreach (['Owner', 'Manager', 'Cashier'] as $name) {
            $roles[strtolower($name)] = Role::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);
        }

        return $roles;
    }

    /** @param array<string, Role> $roles */
    private function seedUsers(array $roles): array
    {
        $users = [];

        foreach ([
            'owner' => ['BTEST Owner', 'browser-owner@example.test', 'Owner'],
            'manager' => ['BTEST Manager', 'browser-manager@example.test', 'Manager'],
            'cashier' => ['BTEST Cashier', 'browser-cashier@example.test', 'Cashier'],
        ] as $key => [$name, $email, $roleName]) {
            /** @var User $user */
            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make(self::TEST_PASSWORD),
                    'role' => strtolower($roleName),
                ]
            );
            $user->syncRoles([$roles[strtolower($roleName)]]);
            $users[$key] = $user;
        }

        return $users;
    }

    private function seedSetting(): Setting
    {
        return Setting::updateOrCreate(
            ['id' => 1],
            [
                'store_name' => 'ATRILAK Browser Test Store',
                'store_address' => "99 Test Street\nBangkok 10110",
                'store_phone' => '020000000',
                'tax_number' => 'BTEST-TAX-0001',
                'branch_type' => 'สำนักงานใหญ่',
                'branch_number' => '00000',
            ]
        );
    }

    /** @return array{print: array<int, Product>, cost: Product} */
    private function seedProducts(Category $category, Unit $unit): array
    {
        $printProducts = [];

        for ($index = 1; $index <= 15; $index++) {
            $name = $index === 15
                ? 'BTEST Print Item 15 - Long Product Name for A4 A5 Layout Verification'
                : sprintf('BTEST Print Item %02d', $index);

            $printProducts[] = $this->upsertProduct(
                $category,
                $unit,
                sprintf('BTEST-PRINT-%02d', $index),
                $name,
                120 + $index,
                250 + $index,
                null
            );
        }

        $this->upsertProduct(
            $category,
            $unit,
            'BTEST-IMG-REAL',
            'BTEST Real Image Product',
            120,
            250,
            self::REAL_IMAGE_PATH
        );
        $this->upsertProduct(
            $category,
            $unit,
            'BTEST-IMG-NONE',
            'BTEST Placeholder Product',
            130,
            270,
            null
        );
        $this->upsertProduct(
            $category,
            $unit,
            'BTEST-IMG-MISSING',
            'BTEST Missing Image Product',
            140,
            290,
            'products/browser-test-missing-image.svg'
        );
        $costProduct = $this->upsertProduct(
            $category,
            $unit,
            'BTEST-COST',
            'BTEST Cost Adjustment Product',
            150,
            350,
            null
        );

        return ['print' => $printProducts, 'cost' => $costProduct];
    }

    private function upsertProduct(
        Category $category,
        Unit $unit,
        string $code,
        string $name,
        int $cost,
        int $selling,
        ?string $imagePath
    ): Product {
        /** @var Product $product */
        $product = Product::updateOrCreate(
            ['product_code' => $code],
            [
                'category_id' => $category->id,
                'unit_id' => $unit->id,
                'barcode' => $code,
                'sku' => $code.'-SKU',
                'name' => $name,
                'image_path' => $imagePath,
                'unit' => 'piece',
                'cost_price' => $cost,
                'selling_price' => $selling,
                'stock_qty' => 100,
                'minimum_stock' => 0,
                'vat_enabled' => false,
                'active' => true,
                'remark' => 'Local-only Browser and Print verification fixture.',
            ]
        );

        /** @var ProductUnit $productUnit */
        $productUnit = ProductUnit::updateOrCreate(
            ['product_id' => $product->id, 'unit_id' => $unit->id],
            [
                'conversion_rate' => 1,
                'conversion_confirmed_at' => now(),
                'is_base_unit' => true,
                'is_purchase_unit' => true,
                'is_sale_unit' => true,
                'purchase_price' => $cost,
                'selling_price' => $selling,
                'active' => true,
                'sort_order' => 1,
            ]
        );

        ProductBarcode::updateOrCreate(
            ['barcode' => $code],
            [
                'product_id' => $product->id,
                'product_unit_id' => $productUnit->id,
                'is_default' => true,
                'active' => true,
                'sort_order' => 1,
            ]
        );

        return $product->fresh(['productUnits']);
    }

    /** @return array{0: Customer, 1: CustomerDeliveryAddress} */
    private function seedCustomer(DeliveryZone $zone): array
    {
        /** @var Customer $customer */
        $customer = Customer::updateOrCreate(
            ['code' => 'BTEST-CUSTOMER'],
            [
                'name' => 'BTEST Customer with a Long Delivery Name',
                'phone' => '0812345678',
                'address' => "99 Test Street\nBangkok 10110",
                'tax_number' => 'BTEST-CUSTOMER-TAX',
                'branch_type' => 'สำนักงานใหญ่',
                'branch_number' => '00000',
                'remark' => 'Local-only Browser and Print verification fixture.',
                'active' => true,
            ]
        );

        /** @var CustomerDeliveryAddress $address */
        $address = CustomerDeliveryAddress::updateOrCreate(
            ['customer_id' => $customer->id, 'name' => 'BTEST-SITE'],
            [
                'receiver_name' => 'BTEST Receiver',
                'receiver_phone' => '0898765432',
                'address' => "99 Test Street, Test District\nBangkok 10110",
                'landmark' => 'Opposite the BTEST Community Center',
                'delivery_zone_id' => $zone->id,
                'latitude' => 13.7563000,
                'longitude' => 100.5018000,
                'is_default' => true,
                'remark' => 'Local-only Browser and Print verification fixture.',
            ]
        );

        return [$customer, $address];
    }

    /** @param array<int, Product> $products */
    private function seedPrintSales(
        array $products,
        Customer $customer,
        CustomerDeliveryAddress $address,
        DeliveryZone $zone,
        Setting $setting
    ): void {
        foreach ([1, 5, 10, 15] as $count) {
            $saleNo = sprintf('BTEST-PRINT-%02d', $count);
            $deliveryFee = 150;
            $totalCents = $deliveryFee * 100;

            /** @var Sale $sale */
            $sale = Sale::updateOrCreate(
                ['sale_no' => $saleNo],
                [
                    'idempotency_key' => sprintf('00000000-0000-4000-8000-%012d', $count),
                    'idempotency_payload_hash' => hash('sha256', 'BTEST-PRINT-'.$count),
                    'customer_id' => $customer->id,
                    'customer_delivery_address_id' => $address->id,
                    'sale_date' => now()->toDateString(),
                    'delivery_zone_id' => $zone->id,
                    'delivery_zone_name_snapshot' => $zone->name,
                    'delivery_zone_markup_percent_snapshot' => 0,
                    'delivery_zone_rounding_increment_snapshot' => 1,
                    'delivery_zone_minimum_profit_snapshot' => 0,
                    'delivery_type' => 'delivery',
                    'discount' => 0,
                    'delivery_fee' => $deliveryFee,
                    'status' => Sale::STATUS_ACTIVE,
                    'payment_method' => 'promptpay',
                    'cash_amount' => 0,
                    'promptpay_amount' => 0,
                    'received_amount' => 0,
                    'change_amount' => 0,
                    'store_name_snapshot' => $setting->store_name,
                    'store_address_snapshot' => $setting->store_address,
                    'store_phone_snapshot' => $setting->store_phone,
                    'store_tax_number_snapshot' => $setting->tax_number,
                    'store_branch_type_snapshot' => $setting->branch_type,
                    'store_branch_number_snapshot' => $setting->branch_number,
                    'customer_name_snapshot' => $customer->name,
                    'customer_phone_snapshot' => $customer->phone,
                    'customer_address_snapshot' => $customer->address,
                    'customer_tax_number_snapshot' => $customer->tax_number,
                    'customer_branch_type_snapshot' => $customer->branch_type,
                    'customer_branch_number_snapshot' => $customer->branch_number,
                    'delivery_address_name_snapshot' => $address->name,
                    'delivery_receiver_name_snapshot' => $address->receiver_name,
                    'delivery_receiver_phone_snapshot' => $address->receiver_phone,
                    'delivery_full_address_snapshot' => $address->address,
                    'delivery_landmark_snapshot' => $address->landmark,
                    'notes' => 'BTEST delivery-note fixture for '.$count.' item(s).',
                    'total_amount' => 0,
                ]
            );

            $sale->items()->delete();

            foreach (array_slice($products, 0, $count) as $product) {
                $productUnit = $product->productUnits->first();
                $priceCents = (int) round((float) $product->selling_price * 100);
                $costCents = (int) round((float) $product->cost_price * 100);
                $totalCents += $priceCents;

                $sale->items()->create([
                    'product_id' => $product->id,
                    'product_unit_id' => $productUnit?->id,
                    'conversion_rate_used' => 1,
                    'base_qty' => 1,
                    'qty' => 1,
                    'selling_price' => $product->selling_price,
                    'original_price' => $product->selling_price,
                    'price_override_flag' => false,
                    'total' => number_format($priceCents / 100, 2, '.', ''),
                    'cost_price' => $product->cost_price,
                    'profit' => number_format(($priceCents - $costCents) / 100, 2, '.', ''),
                    'product_name_snapshot' => $product->name,
                    'product_sku_snapshot' => $product->sku,
                    'product_code_snapshot' => $product->product_code,
                    'unit_name_snapshot' => 'piece',
                    'unit_code_snapshot' => 'BTEST',
                ]);
            }

            $total = number_format($totalCents / 100, 2, '.', '');
            $sale->update([
                'total_amount' => $total,
                'promptpay_amount' => $total,
                'received_amount' => $total,
            ]);
        }
    }

    private function seedHoldBill(
        Product $product,
        User $user,
        Customer $customer,
        CustomerDeliveryAddress $address,
        DeliveryZone $zone
    ): void {
        $productUnit = $product->productUnits->first();
        $total = number_format((float) $product->selling_price + 150, 2, '.', '');

        /** @var HoldBill $holdBill */
        $holdBill = HoldBill::updateOrCreate(
            ['hold_no' => 'BTEST-HOLD-01'],
            [
                'user_id' => $user->id,
                'customer_id' => $customer->id,
                'customer_delivery_address_id' => $address->id,
                'delivery_zone_id' => $zone->id,
                'delivery_zone_name_snapshot' => $zone->name,
                'delivery_zone_markup_percent_snapshot' => 0,
                'delivery_zone_rounding_increment_snapshot' => 1,
                'delivery_zone_minimum_profit_snapshot' => 0,
                'sale_date' => now()->toDateString(),
                'delivery_type' => 'delivery',
                'discount' => 0,
                'delivery_fee' => 150,
                'total_amount' => $total,
                'notes' => 'BTEST Hold and Resume fixture.',
            ]
        );

        $holdBill->items()->delete();
        $holdBill->items()->create([
            'product_id' => $product->id,
            'product_unit_id' => $productUnit?->id,
            'product_unit_id_snapshot' => $productUnit?->id,
            'qty' => 1,
            'selling_price' => $product->selling_price,
            'original_price' => $product->selling_price,
            'price_override_flag' => false,
            'product_name_snapshot' => $product->name,
            'product_sku_snapshot' => $product->sku,
            'product_code_snapshot' => $product->product_code,
            'unit_name_snapshot' => 'piece',
            'unit_code_snapshot' => 'BTEST',
        ]);
    }

    private function writeRealImageFixture(): void
    {
        $contents = file_get_contents(base_path('tests/Fixtures/pos-browser-real-image.svg'));

        if ($contents === false || ! Storage::disk('public')->put(self::REAL_IMAGE_PATH, $contents)) {
            throw new RuntimeException('Unable to write the Browser test image fixture.');
        }
    }
}
