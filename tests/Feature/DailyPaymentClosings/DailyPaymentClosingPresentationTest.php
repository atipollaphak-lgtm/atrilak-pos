<?php

namespace Tests\Feature\DailyPaymentClosings;

use App\Models\DailyPaymentClosing;
use App\Models\DailyPaymentClosingSale;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyPaymentClosingPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_view_history_and_existing_create_redirects_to_the_edit_record(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $older = $this->closing('2026-07-17');
        $latest = $this->closing('2026-07-18', ['status' => DailyPaymentClosing::STATUS_FINALIZED]);

        $this->actingAs($manager)
            ->get(route('daily-payment-closings.index'))
            ->assertOk()
            ->assertSeeInOrder([$latest->business_date, $older->business_date])
            ->assertSeeText('finalized');

        $this->actingAs($manager)
            ->get(route('daily-payment-closings.create', ['business_date' => $latest->business_date]))
            ->assertRedirect(route('daily-payment-closings.edit', $latest));
    }

    public function test_manager_can_open_a_new_closing_form_with_live_totals_and_payment_exceptions(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $this->sale('LIVE-CASH', ['cash_amount' => '20.00', 'received_amount' => '30.00', 'change_amount' => '10.00']);
        $this->sale('LIVE-INVALID', ['payment_method' => null, 'cash_amount' => null, 'promptpay_amount' => null, 'received_amount' => null, 'change_amount' => null]);

        $this->actingAs($manager)
            ->followingRedirects()
            ->get(route('daily-payment-closings.create', ['business_date' => '2026-07-18']))
            ->assertOk()
            ->assertSee('20.00')
            ->assertSee('LIVE-INVALID')
            ->assertSee('disabled');
    }

    public function test_finalize_control_stays_disabled_until_actual_amounts_are_saved(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);

        $this->actingAs($manager)
            ->followingRedirects()
            ->get(route('daily-payment-closings.create', ['business_date' => '2026-07-19']))
            ->assertOk()
            ->assertSee('disabled');
    }

    public function test_manager_can_view_finalized_snapshot_and_print_store_identity_without_live_totals(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $owner = User::factory()->create(['role' => 'owner', 'name' => 'Owner One']);
        $closing = $this->closing('2026-07-18', [
            'status' => DailyPaymentClosing::STATUS_FINALIZED,
            'expected_cash_amount' => '50.00',
            'actual_cash_amount' => '55.00',
            'cash_variance' => '5.00',
            'expected_promptpay_amount' => '25.00',
            'actual_promptpay_amount' => '25.00',
            'promptpay_variance' => '0.00',
            'expected_recorded_sales_amount' => '75.00',
            'expected_received_cash_amount' => '60.00',
            'expected_change_amount' => '10.00',
            'cash_sales_count' => 2,
            'promptpay_sales_count' => 1,
            'mixed_sales_count' => 1,
            'notes' => 'Counted after closing',
            'finalized_by' => $owner->id,
            'finalized_at' => now(),
        ]);
        $sale = $this->sale('SNAPSHOT-SALE', ['sale_date' => '2026-07-17']);
        DailyPaymentClosingSale::query()->create([
            'daily_payment_closing_id' => $closing->id,
            'sale_id' => $sale->id,
            'sale_revision' => 1,
            'sale_status' => Sale::STATUS_ACTIVE,
            'sale_total_amount' => '75.00',
            'payment_method' => 'mixed',
            'cash_amount' => '50.00',
            'promptpay_amount' => '25.00',
            'received_amount' => '60.00',
            'change_amount' => '10.00',
        ]);
        Setting::query()->create([
            'store_name' => 'ATRILAK Test Store',
            'store_address' => 'Test Address',
            'store_phone' => '020000000',
        ]);

        $this->actingAs($manager)
            ->get(route('daily-payment-closings.show', $closing))
            ->assertOk()
            ->assertSee('50.00')
            ->assertSee('Counted after closing')
            ->assertSee('Owner One')
            ->assertSee('SNAPSHOT-SALE');

        $this->actingAs($manager)
            ->get(route('daily-payment-closings.print', $closing))
            ->assertOk()
            ->assertSee('ATRILAK Test Store')
            ->assertSee('75.00')
            ->assertSee('Counted after closing')
            ->assertDontSee('SNAPSHOT-SALE')
            ->assertDontSee('LIVE-CASH');
    }

    public function test_html_routes_enforce_manager_owner_access_and_reserve_reopen_for_owner(): void
    {
        $closing = $this->closing('2026-07-18', ['status' => DailyPaymentClosing::STATUS_FINALIZED]);
        $cashier = User::factory()->create(['role' => 'cashier']);
        $manager = User::factory()->create(['role' => 'manager']);
        $owner = User::factory()->create(['role' => 'owner']);

        $this->get(route('daily-payment-closings.index'))->assertRedirect(route('login'));
        $this->actingAs($cashier)->get(route('daily-payment-closings.index'))->assertForbidden();
        $this->actingAs($manager)->get(route('daily-payment-closings.show', $closing))->assertOk();
        $this->actingAs($manager)
            ->post(route('daily-payment-closings.reopen', $closing), ['reason' => 'recount', 'revision' => 1])
            ->assertForbidden();
        $this->actingAs($owner)->get(route('daily-payment-closings.show', $closing))->assertOk();
    }

    private function closing(string $date, array $attributes = []): DailyPaymentClosing
    {
        return DailyPaymentClosing::query()->create(array_merge([
            'business_date' => $date,
            'status' => DailyPaymentClosing::STATUS_OPEN,
            'revision' => 1,
        ], $attributes));
    }

    private function sale(string $saleNo, array $attributes = []): Sale
    {
        return Sale::query()->create(array_merge([
            'sale_no' => $saleNo,
            'sale_date' => '2026-07-18',
            'total_amount' => '20.00',
            'delivery_fee' => '0.00',
            'discount' => '0.00',
            'delivery_type' => 'pickup',
            'status' => Sale::STATUS_ACTIVE,
            'revision' => 1,
            'payment_method' => 'cash',
            'cash_amount' => '20.00',
            'promptpay_amount' => '0.00',
            'received_amount' => '20.00',
            'change_amount' => '0.00',
        ], $attributes));
    }
}
