<?php

namespace Tests\Feature\Sales;

use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\CommercialDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleVoidPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_keeps_voided_sales_visible_and_shows_void_controls_only_to_manager_or_owner(): void
    {
        $active = $this->sale('SAL-VOID-PRESENT-ACTIVE');
        $actor = $this->user('manager', 'Void Manager');
        $voided = $this->sale('SAL-VOID-PRESENT-VOIDED', [
            'status' => Sale::STATUS_VOIDED,
            'voided_at' => now(),
            'voided_by' => $actor->id,
            'void_reason' => 'Customer requested cancellation',
        ]);

        $managerHtml = $this->actingAs($actor)
            ->get(route('sales.history'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($active->sale_no, $managerHtml);
        $this->assertStringContainsString($voided->sale_no, $managerHtml);
        $this->assertStringContainsString('ยกเลิก', $managerHtml);
        $this->assertStringContainsString(route('sales.void', $active), $managerHtml);
        $this->assertStringNotContainsString(route('sales.edit', $voided), $managerHtml);
        $this->assertStringNotContainsString(route('sales.void', $voided), $managerHtml);
        $this->assertStringNotContainsString('sales.destroy', $managerHtml);

        $ownerHtml = $this->actingAs($this->user('owner', 'Void Owner History'))
            ->get(route('sales.history'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(route('sales.void', $active), $ownerHtml);

        $cashierHtml = $this->actingAs($this->user('cashier', 'Void Cashier'))
            ->get(route('sales.history'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString(route('sales.void', $active), $cashierHtml);
    }

    public function test_detail_shows_void_audit_metadata_and_hides_edit_and_void_actions(): void
    {
        $actor = $this->user('owner', 'Void Owner');
        $voided = $this->sale('SAL-VOID-PRESENT-DETAIL', [
            'status' => Sale::STATUS_VOIDED,
            'voided_at' => now(),
            'voided_by' => $actor->id,
            'void_reason' => 'Incorrect sale entry',
            'payment_method' => 'cash',
            'cash_amount' => '10.00',
            'promptpay_amount' => '0.00',
            'received_amount' => '20.00',
            'change_amount' => '10.00',
        ]);
        $this->addItem($voided);

        $html = $this->actingAs($actor)
            ->get(route('sales.show', $voided))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('ยกเลิก / VOID', $html);
        $this->assertStringContainsString('Incorrect sale entry', $html);
        $this->assertStringContainsString('Void Owner', $html);
        $this->assertStringContainsString('เงินสด', $html);
        $this->assertStringNotContainsString(route('sales.edit', $voided), $html);
        $this->assertStringNotContainsString(route('sales.void', $voided), $html);
    }

    public function test_void_form_posts_reason_with_csrf_required_length_and_warning(): void
    {
        $sale = $this->sale('SAL-VOID-PRESENT-FORM');
        $html = $this->actingAs($this->user('manager', 'Form Manager'))
            ->get(route('sales.show', $sale))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('action="'.route('sales.void', $sale).'"', $html);
        $this->assertStringContainsString('name="void_reason"', $html);
        $this->assertStringContainsString('name="_token"', $html);
        $this->assertStringContainsString('required', $html);
        $this->assertStringContainsString('maxlength="1000"', $html);
        $this->assertStringContainsString('สต็อกจะถูกคืน', $html);
        $this->assertStringContainsString('ไม่สามารถย้อนกลับได้', $html);
    }

    public function test_detail_uses_a_safe_fallback_when_the_voiding_user_is_unavailable(): void
    {
        $voided = $this->sale('SAL-VOID-PRESENT-NO-ACTOR', [
            'status' => Sale::STATUS_VOIDED,
            'voided_at' => now(),
            'voided_by' => null,
            'void_reason' => 'Legacy void record',
        ]);

        $html = $this->actingAs($this->user('cashier', 'Fallback Cashier'))
            ->get(route('sales.show', $voided))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Legacy void record', $html);
        $this->assertStringContainsString('ไม่ระบุ', $html);
    }

    public function test_voided_documents_show_marker_while_active_documents_do_not(): void
    {
        $active = $this->sale('SAL-VOID-PRESENT-DOC-ACTIVE');
        $voided = $this->sale('SAL-VOID-PRESENT-DOC-VOIDED', [
            'status' => Sale::STATUS_VOIDED,
            'voided_at' => now(),
            'void_reason' => 'Document void',
        ]);
        $this->addItem($active);
        $this->addItem($voided);
        $documents = app(CommercialDocumentService::class);

        foreach ([
            view('sales.invoice', ['sale' => $active->fresh('items.product'), 'setting' => null])->render(),
            view('sales.print', ['sale' => $active->fresh('items.product'), 'setting' => null])->render(),
            view('sales.invoice_v2', [
                'sale' => $active->fresh('items.product'),
                'setting' => null,
                'document' => $documents->buildSaleDocument($active, 'tax-invoice'),
            ])->render(),
        ] as $html) {
            $this->assertStringNotContainsString('ยกเลิก / VOID', $html);
        }

        foreach ([
            view('sales.invoice', ['sale' => $voided->fresh('items.product'), 'setting' => null])->render(),
            view('sales.print', ['sale' => $voided->fresh('items.product'), 'setting' => null])->render(),
            view('sales.invoice_v2', [
                'sale' => $voided->fresh('items.product'),
                'setting' => null,
                'document' => $documents->buildSaleDocument($voided, 'delivery-note'),
            ])->render(),
            view('sales.invoice_v2', [
                'sale' => $voided->fresh('items.product'),
                'setting' => null,
                'document' => $documents->buildSaleDocument($voided, 'tax-invoice'),
            ])->render(),
        ] as $html) {
            $this->assertStringContainsString('ยกเลิก / VOID', $html);
            $this->assertStringContainsString('SAL-VOID-PRESENT-DOC-VOIDED', $html);
        }
    }

    private function user(string $role, string $name): User
    {
        return User::factory()->create(['name' => $name, 'role' => $role]);
    }

    private function sale(string $saleNo, array $attributes = []): Sale
    {
        return Sale::query()->create(array_merge([
            'sale_no' => $saleNo,
            'sale_date' => '2026-07-18',
            'total_amount' => '10.00',
            'delivery_fee' => '0.00',
            'delivery_type' => 'pickup',
            'discount' => '0.00',
        ], $attributes));
    }

    private function addItem(Sale $sale): void
    {
        $product = Product::query()->create([
            'category_id' => Category::query()->create([
                'name' => 'Void presentation category '.$sale->sale_no,
            ])->id,
            'name' => 'Void presentation product '.$sale->sale_no,
            'cost_price' => '5.00',
            'selling_price' => '10.00',
            'stock_qty' => '10.0000',
            'active' => true,
        ]);

        $sale->items()->create([
            'product_id' => $product->id,
            'qty' => '1.00',
            'base_qty' => '1.0000',
            'conversion_rate_used' => '1.0000',
            'selling_price' => '10.00',
            'cost_price' => '5.00',
            'total' => '10.00',
            'profit' => '5.00',
        ]);
    }
}
