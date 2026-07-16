<?php

namespace Tests\Feature\Documents;

use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerDeliveryAddress;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Quotation;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\Technician;
use App\Models\Unit;
use App\Services\CommercialDocumentService;
use App\Services\SaleService;
use App\Services\TransactionDocumentSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TransactionDocumentSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_sale_stores_header_item_and_selected_unit_snapshots(): void
    {
        $context = $this->context();
        $sale = $this->createSale($context);
        $item = $sale->items()->sole();

        $this->assertSame('Snapshot Store', $sale->store_name_snapshot);
        $this->assertSame('STORE-TAX', $sale->store_tax_number_snapshot);
        $this->assertSame('Snapshot Customer', $sale->customer_name_snapshot);
        $this->assertSame('CUSTOMER-TAX', $sale->customer_tax_number_snapshot);
        $this->assertSame('Snapshot Technician', $sale->technician_name_snapshot);
        $this->assertSame('Snapshot Site', $sale->delivery_address_name_snapshot);
        $this->assertSame('Snapshot Receiver', $sale->delivery_receiver_name_snapshot);
        $this->assertSame('Snapshot Product', $item->product_name_snapshot);
        $this->assertSame('SKU-SNAPSHOT', $item->product_sku_snapshot);
        $this->assertSame('PRODUCT-SNAPSHOT', $item->product_code_snapshot);
        $this->assertSame('Snapshot Pack', $item->unit_name_snapshot);
        $this->assertSame('PACK', $item->unit_code_snapshot);
        $this->assertSame('2.00', $item->qty);
        $this->assertSame('60.00', $item->selling_price);
        $this->assertSame('120.00', $item->total);
        $this->assertSame('4.00', $item->cost_price);
        $this->assertSame('24.00', $item->profit);
        $this->assertSame('12.0000', $item->conversion_rate_used);
        $this->assertSame('24.0000', $item->base_qty);
    }

    public function test_v1_factor_one_snapshots_legacy_product_unit_without_creating_product_unit_reference(): void
    {
        $context = $this->context();
        $sale = $this->createSale($context, null, null, false);
        $item = $sale->items()->sole();

        $this->assertNull($item->product_unit_id);
        $this->assertSame('Snapshot Piece', $item->unit_name_snapshot);
        $this->assertSame('PCS', $item->unit_code_snapshot);
        $this->assertSame('1.0000', $item->conversion_rate_used);
        $this->assertSame('2.0000', $item->base_qty);
    }

    public function test_sale_documents_use_snapshots_after_master_changes_and_relations_are_removed(): void
    {
        $context = $this->context();
        $sale = $this->createSale($context);

        $context['product']->update(['name' => 'Changed Product']);
        $context['product']->forceFill([
            'sku' => 'CHANGED-SKU',
            'product_code' => 'CHANGED-CODE',
        ])->save();
        $context['unit']->update(['name' => 'Changed Pack']);
        $context['customer']->update([
            'name' => 'Changed Customer',
            'phone' => '0999999999',
            'address' => 'Changed Customer Address',
            'tax_number' => 'CHANGED-TAX',
        ]);
        $context['setting']->update(['store_name' => 'Changed Store', 'tax_number' => 'CHANGED-STORE-TAX']);
        $context['technician']->update(['name' => 'Changed Technician']);
        $context['address']->update([
            'name' => 'Changed Site',
            'receiver_name' => 'Changed Receiver',
            'receiver_phone' => '0999999998',
            'address' => 'Changed Delivery Address',
            'landmark' => 'Changed Landmark',
        ]);
        $context['productUnit']->delete();
        $context['technician']->delete();
        $context['address']->delete();
        $context['customer']->delete();

        $sale = $sale->fresh()->load([
            'customer',
            'customerDeliveryAddress',
            'technician',
            'items.product.unitRelation',
            'items.productUnit.unit',
        ]);
        $setting = Setting::query()->first();
        $documents = collect(['delivery-note', 'tax-invoice', 'quotation'])
            ->mapWithKeys(function (string $type) use ($sale, $setting): array {
                $document = app(CommercialDocumentService::class)
                    ->buildSaleDocument($sale, $type);

                return [$type => view(
                    'sales.invoice_v2',
                    compact('sale', 'setting', 'document')
                )->render()];
            });
        $documents->put('legacy-invoice', view(
            'sales.invoice',
            compact('sale', 'setting')
        )->render());
        $documents->put('sale-print', view(
            'sales.print',
            compact('sale', 'setting')
        )->render());

        foreach ($documents as $renderer => $html) {
            $this->assertStringContainsString('Snapshot Store', $html, $renderer);
            $this->assertStringContainsString('Snapshot Customer', $html, $renderer);
            $this->assertStringContainsString('Snapshot Product', $html, $renderer);
            $this->assertStringNotContainsString('Changed Store', $html, $renderer);
            $this->assertStringNotContainsString('Changed Customer', $html, $renderer);
            $this->assertStringNotContainsString('Changed Product', $html, $renderer);
        }

        foreach (['delivery-note', 'tax-invoice', 'quotation', 'legacy-invoice'] as $renderer) {
            $this->assertStringContainsString('Snapshot Pack', $documents[$renderer], $renderer);
        }

        $this->assertStringContainsString('STORE-TAX', $documents['tax-invoice']);
        $this->assertStringContainsString('CUSTOMER-TAX', $documents['tax-invoice']);
        $this->assertStringContainsString('Snapshot Technician', $documents['legacy-invoice']);
        $this->assertStringNotContainsString('Changed Technician', $documents['legacy-invoice']);
        $this->assertNull($sale->customer_id);
        $this->assertNull($sale->technician_id);
        $this->assertNull($sale->customer_delivery_address_id);
        $this->assertNull($sale->items->sole()->product_unit_id);
        $this->assertSame('Snapshot Site', $sale->delivery_address_name_snapshot);
        $this->assertSame('Snapshot Receiver', $sale->delivery_receiver_name_snapshot);
        $this->assertSame('Snapshot Delivery Address', $sale->delivery_full_address_snapshot);
        $this->assertSame('Snapshot Landmark', $sale->delivery_landmark_snapshot);
        $this->assertSame('SKU-SNAPSHOT', $sale->items->sole()->product_sku_snapshot);
        $this->assertSame('PRODUCT-SNAPSHOT', $sale->items->sole()->product_code_snapshot);
    }

    public function test_legacy_sale_with_null_snapshots_falls_back_to_current_relations(): void
    {
        $context = $this->context();
        $sale = Sale::query()->create([
            'sale_no' => 'SAL-LEGACY',
            'customer_id' => $context['customer']->id,
            'sale_date' => '2026-07-15',
            'total_amount' => '20.00',
            'delivery_fee' => '0.00',
            'delivery_type' => 'pickup',
            'discount' => '0.00',
        ]);
        $sale->items()->create([
            'product_id' => $context['product']->id,
            'product_unit_id' => $context['productUnit']->id,
            'qty' => '2.00',
            'selling_price' => '10.00',
            'total' => '20.00',
            'cost_price' => '4.00',
            'profit' => '12.00',
        ]);
        $sale->load(['customer', 'items.product.unitRelation', 'items.productUnit.unit']);
        $setting = $context['setting'];
        $documents = collect(['delivery-note', 'tax-invoice', 'quotation'])
            ->mapWithKeys(function (string $type) use ($sale, $setting): array {
                $document = app(CommercialDocumentService::class)
                    ->buildSaleDocument($sale, $type);

                return [$type => view(
                    'sales.invoice_v2',
                    compact('sale', 'setting', 'document')
                )->render()];
            });
        $documents->put('legacy-invoice', view(
            'sales.invoice',
            compact('sale', 'setting')
        )->render());
        $documents->put('sale-print', view(
            'sales.print',
            compact('sale', 'setting')
        )->render());

        foreach ($documents as $renderer => $html) {
            $this->assertStringContainsString('Snapshot Store', $html, $renderer);
            $this->assertStringContainsString('Snapshot Customer', $html, $renderer);
            $this->assertStringContainsString('Snapshot Product', $html, $renderer);
        }

        foreach (['delivery-note', 'tax-invoice', 'quotation', 'legacy-invoice'] as $renderer) {
            $this->assertStringContainsString('Snapshot Pack', $documents[$renderer], $renderer);
        }
    }

    public function test_sale_update_recaptures_customer_and_preserves_same_identity_item_snapshots(): void
    {
        $context = $this->context();
        $second = $this->product('Second Product', 'Second Unit', 'SECOND');
        $sale = $this->createSale($context, $context['productUnit'], [
            $this->line($context['product'], $context['productUnit'], '2.00', '60.00'),
            $this->line($second['product'], $second['productUnit'], '1.00', '60.00'),
        ]);
        $items = $sale->items()->orderBy('id')->get();
        $newCustomer = Customer::query()->create([
            'name' => 'Replacement Customer',
            'phone' => '099',
            'address' => 'Replacement Address',
            'active' => true,
        ]);

        $context['product']->update(['name' => 'Changed Product']);
        $second['product']->update(['name' => 'Changed Second Product']);
        $context['setting']->update(['store_name' => 'Changed Store']);

        $updated = app(SaleService::class)->updateSale($sale, [
            'customer_id' => $newCustomer->id,
            'sale_date' => '2026-07-16',
            'delivery_fee' => '0.00',
            'discount' => '0.00',
            'items' => [
                $this->submittedLine($items[0], '3.00'),
                $this->submittedLine($items[1], '1.00'),
            ],
        ])->fresh();
        $updatedItems = $updated->items()->orderBy('id')->get();

        $this->assertSame('Replacement Customer', $updated->customer_name_snapshot);
        $this->assertSame('Snapshot Store', $updated->store_name_snapshot);
        $this->assertSame('Snapshot Product', $updatedItems[0]->product_name_snapshot);
        $this->assertSame('Second Product', $updatedItems[1]->product_name_snapshot);
    }

    public function test_header_only_update_keeps_existing_item_snapshots(): void
    {
        $context = $this->context();
        $sale = $this->createSale($context);
        $item = $sale->items()->sole();
        $context['product']->update(['name' => 'Changed Product']);

        app(SaleService::class)->updateSale($sale, [
            'customer_id' => $sale->customer_id,
            'sale_date' => '2026-07-16',
            'delivery_fee' => '0.00',
            'discount' => '0.00',
            'items' => [$this->submittedLine($item, '2.00')],
        ]);

        $this->assertSame($item->id, $sale->fresh()->items()->sole()->id);
        $this->assertSame('Snapshot Product', $sale->fresh()->items()->sole()->product_name_snapshot);
    }

    public function test_quotation_creation_and_print_use_store_customer_and_product_snapshots(): void
    {
        $context = $this->context();
        $this->withoutMiddleware()->post(route('quotations.store'), [
            'quotation_date' => '2026-07-15',
            'customer_id' => $context['customer']->id,
            'remark' => 'Snapshot quotation',
            'product_id' => [$context['product']->id],
            'qty' => ['2'],
            'selling_price' => ['10.00'],
        ])->assertRedirect(route('quotations.index'));

        $quotation = Quotation::query()->latest('id')->firstOrFail();
        $this->assertSame('Snapshot Store', $quotation->store_name_snapshot);
        $this->assertSame('Snapshot Customer', $quotation->customer_name_snapshot);
        $this->assertSame('CUSTOMER-TAX', $quotation->customer_tax_number_snapshot);
        $quotationItem = $quotation->items()->sole();
        $this->assertSame('Snapshot Product', $quotationItem->product_name_snapshot);
        $this->assertSame('SKU-SNAPSHOT', $quotationItem->product_sku_snapshot);
        $this->assertSame('PRODUCT-SNAPSHOT', $quotationItem->product_code_snapshot);

        $context['setting']->update(['store_name' => 'Changed Store']);
        $context['product']->update(['name' => 'Changed Product']);
        $context['customer']->delete();

        $quotation = $quotation->fresh()->load(['customer', 'items.product']);
        $html = view('quotations.print', compact('quotation'))->render();

        $this->assertStringContainsString('Snapshot Store', $html);
        $this->assertStringContainsString('Snapshot Customer', $html);
        $this->assertStringContainsString('Snapshot Product', $html);
        $this->assertStringNotContainsString('Changed Store', $html);
        $this->assertStringNotContainsString('Changed Product', $html);
        $this->assertNull($quotation->customer_id);
    }

    public function test_sale_item_snapshot_master_queries_are_batched(): void
    {
        $context = $this->context();
        $second = $this->product('Second Product', 'Second Unit', 'SECOND');
        $products = collect([$context['product'], $second['product']])->keyBy('id');
        $items = [
            $this->line($context['product'], $context['productUnit'], '1.00', '10.00'),
            $this->line($context['product'], $context['productUnit'], '2.00', '10.00'),
            $this->line($second['product'], $second['productUnit'], '1.00', '15.00'),
            $this->line($second['product'], $second['productUnit'], '2.00', '15.00'),
        ];

        DB::flushQueryLog();
        DB::enableQueryLog();
        app(TransactionDocumentSnapshotService::class)
            ->saleItemSnapshots($items, $products);
        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        $this->assertCount(1, $queries->filter(
            fn (string $query) => str_contains($query, 'from "product_units"')
        ));
        $this->assertCount(1, $queries->filter(
            fn (string $query) => str_contains($query, 'from "units"')
        ));
    }

    private function context(): array
    {
        $setting = Setting::query()->create([
            'store_name' => 'Snapshot Store',
            'store_address' => 'Snapshot Store Address',
            'store_phone' => '020000000',
            'tax_number' => 'STORE-TAX',
            'branch_type' => 'head_office',
        ]);
        $customer = Customer::query()->create([
            'name' => 'Snapshot Customer',
            'phone' => '0800000000',
            'address' => 'Snapshot Customer Address',
            'tax_number' => 'CUSTOMER-TAX',
            'active' => true,
        ]);
        $technician = Technician::query()->create([
            'name' => 'Snapshot Technician',
            'active' => true,
        ]);
        $address = CustomerDeliveryAddress::query()->create([
            'customer_id' => $customer->id,
            'name' => 'Snapshot Site',
            'receiver_name' => 'Snapshot Receiver',
            'receiver_phone' => '0811111111',
            'address' => 'Snapshot Delivery Address',
            'landmark' => 'Snapshot Landmark',
        ]);
        $product = $this->product('Snapshot Product', 'Snapshot Pack', 'PACK');

        return array_merge($product, compact('setting', 'customer', 'technician', 'address'));
    }

    private function product(string $name, string $unitName, string $unitCode): array
    {
        $category = Category::query()->create(['name' => $name.' Category']);
        $baseUnit = Unit::query()->firstOrCreate(
            ['code' => 'PCS'],
            ['name' => 'Snapshot Piece', 'short_name' => 'PCS']
        );
        $unit = Unit::query()->create([
            'name' => $unitName,
            'code' => $unitCode,
            'short_name' => $unitCode,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'unit_id' => $baseUnit->id,
            'name' => $name,
            'unit' => 'Legacy Piece',
            'cost_price' => '4.00',
            'selling_price' => '10.00',
            'stock_qty' => '100.0000',
            'minimum_stock' => '0.0000',
            'active' => true,
        ]);
        $product->forceFill([
            'sku' => 'SKU-SNAPSHOT',
            'product_code' => 'PRODUCT-SNAPSHOT',
        ])->save();
        $productUnit = ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'conversion_rate' => '12.0000',
            'conversion_confirmed_at' => now(),
            'is_base_unit' => false,
            'is_sale_unit' => true,
            'is_purchase_unit' => false,
            'active' => true,
        ]);

        return compact('product', 'productUnit', 'unit', 'baseUnit');
    }

    private function createSale(
        array $context,
        ?ProductUnit $productUnit = null,
        ?array $items = null,
        bool $useDefaultProductUnit = true
    ): Sale {
        if ($useDefaultProductUnit) {
            $productUnit ??= $context['productUnit'];
        }
        $items ??= [$this->line($context['product'], $productUnit, '2.00', '60.00')];

        return app(SaleService::class)->createSale([
            'customer_id' => $context['customer']->id,
            'customer_delivery_address_id' => $context['address']->id,
            'technician_id' => $context['technician']->id,
            'sale_date' => '2026-07-15',
            'delivery_type' => 'pickup',
            'discount' => '0.00',
            'items' => $items,
        ]);
    }

    private function line(
        Product $product,
        ?ProductUnit $productUnit,
        string $qty,
        string $price
    ): array {
        return [
            'product_id' => $product->id,
            'product_unit_id' => $productUnit?->id,
            'qty' => $qty,
            'selling_price' => $price,
        ];
    }

    private function submittedLine($item, string $qty): array
    {
        return [
            'sale_item_id' => $item->id,
            'product_id' => $item->product_id,
            'product_unit_id' => $item->product_unit_id,
            'qty' => $qty,
            'selling_price' => $item->selling_price,
        ];
    }
}
