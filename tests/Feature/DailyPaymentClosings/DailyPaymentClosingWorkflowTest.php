<?php

namespace Tests\Feature\DailyPaymentClosings;

use App\Http\Requests\DailyPaymentClosings\UpdateDailyPaymentClosingRequest;
use App\Models\DailyPaymentClosing;
use App\Models\DailyPaymentClosingSale;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class DailyPaymentClosingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_open_a_daily_payment_closing_once_and_reuse_the_open_record(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);

        $first = $this->actingAs($manager)
            ->postJson(route('daily-payment-closings.store'), ['business_date' => '2026-07-18'])
            ->assertCreated()
            ->json('data');

        $second = $this->actingAs($manager)
            ->postJson(route('daily-payment-closings.store'), ['business_date' => '2026-07-18'])
            ->assertOk()
            ->json('data');

        $this->assertSame($first['id'], $second['id']);
        $this->assertSame(DailyPaymentClosing::STATUS_OPEN, $second['status']);
        $this->assertSame(1, $first['revision']);
        $this->assertSame(1, $second['revision']);
        $this->assertDatabaseCount('daily_payment_closings', 1);
        $this->assertDatabaseHas('daily_payment_closings', [
            'id' => $first['id'],
            'opened_by' => $manager->id,
            'revision' => 1,
        ]);
    }

    public function test_opening_an_existing_finalized_closing_never_overwrites_it(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $closing = DailyPaymentClosing::query()->create([
            'business_date' => '2026-07-18',
            'status' => DailyPaymentClosing::STATUS_FINALIZED,
            'opened_by' => User::factory()->create(['role' => 'owner'])->id,
            'finalized_by' => $manager->id,
            'finalized_at' => now(),
            'revision' => 4,
        ]);

        $this->actingAs($manager)
            ->postJson(route('daily-payment-closings.store'), ['business_date' => '2026-07-18'])
            ->assertOk()
            ->assertJsonPath('data.id', $closing->id)
            ->assertJsonPath('data.status', DailyPaymentClosing::STATUS_FINALIZED)
            ->assertJsonPath('data.revision', 4);
    }

    public function test_update_request_rejects_negative_and_non_scale_two_amounts(): void
    {
        $rules = (new UpdateDailyPaymentClosingRequest)->rules();

        foreach (['-1.00', '1', '1.0', '1.000', 1.0] as $invalid) {
            $validator = Validator::make([
                'actual_cash_amount' => $invalid,
                'actual_promptpay_amount' => '0.00',
                'revision' => 1,
            ], $rules);

            $this->assertTrue($validator->fails(), (string) $invalid);
            $this->assertArrayHasKey('actual_cash_amount', $validator->errors()->toArray());
        }
    }

    public function test_authorization_allows_manager_workflow_but_reserves_reopen_for_owner(): void
    {
        $closing = DailyPaymentClosing::query()->create(['business_date' => '2026-07-18']);
        $payload = ['actual_cash_amount' => '10.00', 'actual_promptpay_amount' => '0.00', 'revision' => 1];

        $this->actingAs(User::factory()->create(['role' => 'cashier']))
            ->putJson(route('daily-payment-closings.update', $closing), $payload)
            ->assertForbidden();

        $manager = User::factory()->create(['role' => 'manager']);
        $this->actingAs($manager)
            ->putJson(route('daily-payment-closings.update', $closing), $payload)
            ->assertOk();
        $this->actingAs($manager)
            ->postJson(route('daily-payment-closings.reopen', $closing), ['reason' => 'fix', 'revision' => 2])
            ->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'owner']))
            ->postJson(route('daily-payment-closings.reopen', $closing), ['reason' => 'fix', 'revision' => 2])
            ->assertStatus(409);
    }

    public function test_manager_updates_open_closing_with_decimal_strings_and_revision_protection(): void
    {
        $closing = DailyPaymentClosing::query()->create(['business_date' => '2026-07-18']);
        $manager = User::factory()->create(['role' => 'manager']);
        $payload = [
            'actual_cash_amount' => '12.34',
            'actual_promptpay_amount' => '56.78',
            'notes' => 'counted by manager',
            'revision' => 1,
        ];

        $this->actingAs($manager)
            ->putJson(route('daily-payment-closings.update', $closing), $payload)
            ->assertOk()
            ->assertJsonPath('data.revision', 2);

        $this->assertDatabaseHas('daily_payment_closings', [
            'id' => $closing->id,
            'actual_cash_amount' => '12.34',
            'actual_promptpay_amount' => '56.78',
            'notes' => 'counted by manager',
            'revision' => 2,
        ]);

        $this->actingAs($manager)
            ->putJson(route('daily-payment-closings.update', $closing), $payload)
            ->assertStatus(409);

    }

    public function test_finalize_snapshots_active_sales_and_keeps_non_zero_variance(): void
    {
        $closing = DailyPaymentClosing::query()->create([
            'business_date' => '2026-07-18',
            'actual_cash_amount' => '15.00',
            'actual_promptpay_amount' => '30.00',
        ]);
        $cash = $this->sale('CLOSE-CASH', ['payment_method' => 'cash', 'cash_amount' => '10.00', 'promptpay_amount' => '0.00', 'received_amount' => '20.00', 'change_amount' => '10.00']);
        $promptpay = $this->sale('CLOSE-PROMPTPAY', ['payment_method' => 'promptpay', 'cash_amount' => '0.00', 'promptpay_amount' => '25.00', 'received_amount' => '0.00', 'change_amount' => '0.00'], '25.00');
        $this->sale('CLOSE-VOIDED', ['status' => Sale::STATUS_VOIDED, 'payment_method' => 'cash', 'cash_amount' => '99.00', 'promptpay_amount' => '0.00', 'received_amount' => '99.00', 'change_amount' => '0.00'], '99.00');
        $manager = User::factory()->create(['role' => 'manager']);

        $this->actingAs($manager)
            ->postJson(route('daily-payment-closings.finalize', $closing), ['revision' => 1])
            ->assertOk()
            ->assertJsonPath('data.status', DailyPaymentClosing::STATUS_FINALIZED)
            ->assertJsonPath('data.revision', 2);

        $this->assertDatabaseHas('daily_payment_closings', [
            'id' => $closing->id,
            'expected_cash_amount' => '10.00',
            'expected_promptpay_amount' => '25.00',
            'expected_recorded_sales_amount' => '35.00',
            'expected_received_cash_amount' => '20.00',
            'expected_change_amount' => '10.00',
            'cash_variance' => '5.00',
            'promptpay_variance' => '5.00',
            'cash_sales_count' => 1,
            'promptpay_sales_count' => 1,
            'finalized_by' => $manager->id,
            'revision' => 2,
        ]);
        $this->assertDatabaseCount('daily_payment_closing_sales', 2);
        $this->assertDatabaseHas('daily_payment_closing_sales', ['daily_payment_closing_id' => $closing->id, 'sale_id' => $cash->id, 'sale_revision' => 1]);
        $this->assertDatabaseHas('daily_payment_closing_sales', ['daily_payment_closing_id' => $closing->id, 'sale_id' => $promptpay->id, 'sale_revision' => 1]);

        $this->actingAs($manager)
            ->postJson(route('daily-payment-closings.finalize', $closing), ['revision' => 2])
            ->assertStatus(409);
        $this->actingAs($manager)
            ->putJson(route('daily-payment-closings.update', $closing), ['actual_cash_amount' => '15.00', 'actual_promptpay_amount' => '30.00', 'revision' => 2])
            ->assertStatus(409);
    }

    public function test_finalize_blocks_invalid_active_payment_data_without_replacing_existing_snapshots(): void
    {
        $closing = DailyPaymentClosing::query()->create(['business_date' => '2026-07-18', 'actual_cash_amount' => '0.00', 'actual_promptpay_amount' => '0.00']);
        $existingSale = $this->sale('CLOSE-EXISTING', ['payment_method' => 'cash', 'cash_amount' => '10.00', 'promptpay_amount' => '0.00', 'received_amount' => '10.00', 'change_amount' => '0.00']);
        DailyPaymentClosingSale::query()->create(['daily_payment_closing_id' => $closing->id, 'sale_id' => $existingSale->id, 'sale_revision' => 1, 'sale_status' => Sale::STATUS_ACTIVE]);
        $this->sale('CLOSE-INVALID', ['payment_method' => null, 'cash_amount' => null, 'promptpay_amount' => null, 'received_amount' => null, 'change_amount' => null]);

        $this->actingAs(User::factory()->create(['role' => 'manager']))
            ->postJson(route('daily-payment-closings.finalize', $closing), ['revision' => 1])
            ->assertUnprocessable();

        $this->assertSame(DailyPaymentClosing::STATUS_OPEN, $closing->fresh()->status);
        $this->assertSame(1, DailyPaymentClosingSale::query()->where('daily_payment_closing_id', $closing->id)->count());
    }

    public function test_owner_reopens_without_erasing_finalization_evidence_and_refinalize_replaces_snapshots(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $owner = User::factory()->create(['role' => 'owner']);
        $closing = DailyPaymentClosing::query()->create(['business_date' => '2026-07-18', 'actual_cash_amount' => '10.00', 'actual_promptpay_amount' => '0.00']);
        $firstSale = $this->sale('CLOSE-FIRST', ['payment_method' => 'cash', 'cash_amount' => '10.00', 'promptpay_amount' => '0.00', 'received_amount' => '10.00', 'change_amount' => '0.00']);
        $this->actingAs($manager)->postJson(route('daily-payment-closings.finalize', $closing), ['revision' => 1])->assertOk();
        $firstFinalizedAt = $closing->fresh()->finalized_at;

        $this->actingAs($owner)
            ->postJson(route('daily-payment-closings.reopen', $closing), ['reason' => '  recount required  ', 'revision' => 2])
            ->assertOk()
            ->assertJsonPath('data.status', DailyPaymentClosing::STATUS_OPEN)
            ->assertJsonPath('data.revision', 3);

        $reopened = $closing->fresh();
        $this->assertSame($manager->id, $reopened->finalized_by);
        $this->assertTrue($firstFinalizedAt->equalTo($reopened->finalized_at));
        $this->assertSame($owner->id, $reopened->reopened_by);
        $this->assertSame('recount required', $reopened->reopen_reason);
        $this->assertDatabaseCount('daily_payment_closing_sales', 1);

        $secondSale = $this->sale('CLOSE-SECOND', ['payment_method' => 'cash', 'cash_amount' => '5.00', 'promptpay_amount' => '0.00', 'received_amount' => '5.00', 'change_amount' => '0.00'], '5.00');
        $this->actingAs($manager)
            ->putJson(route('daily-payment-closings.update', $closing), ['actual_cash_amount' => '15.00', 'actual_promptpay_amount' => '0.00', 'revision' => 3])
            ->assertOk();
        $this->actingAs($manager)->postJson(route('daily-payment-closings.finalize', $closing), ['revision' => 4])->assertOk();

        $this->assertSame(2, DailyPaymentClosingSale::query()->where('daily_payment_closing_id', $closing->id)->count());
        $this->assertDatabaseHas('daily_payment_closing_sales', ['daily_payment_closing_id' => $closing->id, 'sale_id' => $firstSale->id]);
        $this->assertDatabaseHas('daily_payment_closing_sales', ['daily_payment_closing_id' => $closing->id, 'sale_id' => $secondSale->id]);
        $this->assertSame(5, $closing->fresh()->revision);
    }

    public function test_no_sales_day_can_finalize_when_actual_amounts_are_recorded(): void
    {
        $closing = DailyPaymentClosing::query()->create(['business_date' => '2026-07-18', 'actual_cash_amount' => '0.00', 'actual_promptpay_amount' => '0.00']);

        $this->actingAs(User::factory()->create(['role' => 'manager']))
            ->postJson(route('daily-payment-closings.finalize', $closing), ['revision' => 1])
            ->assertOk();

        $this->assertSame(DailyPaymentClosing::STATUS_FINALIZED, $closing->fresh()->status);
        $this->assertDatabaseCount('daily_payment_closing_sales', 0);
    }

    private function sale(string $saleNo, array $attributes = [], string $total = '10.00'): Sale
    {
        return Sale::query()->create(array_merge([
            'sale_no' => $saleNo,
            'sale_date' => '2026-07-18',
            'total_amount' => $total,
            'delivery_fee' => '0.00',
            'delivery_type' => 'pickup',
            'discount' => '0.00',
            'status' => Sale::STATUS_ACTIVE,
            'revision' => 1,
        ], $attributes));
    }
}
