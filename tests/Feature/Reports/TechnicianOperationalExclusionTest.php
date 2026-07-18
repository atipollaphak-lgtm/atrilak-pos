<?php

namespace Tests\Feature\Reports;

use App\Http\Controllers\TechnicianCommissionController;
use App\Http\Controllers\TechnicianPaymentController;
use App\Models\Sale;
use App\Models\Technician;
use App\Models\TechnicianCommission;
use App\Models\TechnicianPaymentBatch;
use App\Services\TechnicianPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class TechnicianOperationalExclusionTest extends TestCase
{
    use RefreshDatabase;

    public function test_commission_audit_keeps_history_but_operational_summaries_exclude_voided_sales(): void
    {
        [$technician, $activeCommission, $legacyPendingOnVoidedSale, $voidedCommission, $paidCommission] = $this->records();

        $audit = app(TechnicianCommissionController::class)
            ->index(Request::create('/', 'GET', ['month' => '2026-07']))
            ->getData();
        $payment = app(TechnicianPaymentController::class)
            ->index(Request::create('/', 'GET', ['month' => '2026-07']))
            ->getData();

        $this->assertEqualsCanonicalizing(
            [$activeCommission->id, $legacyPendingOnVoidedSale->id, $voidedCommission->id, $paidCommission->id],
            $audit['commissions']->pluck('id')->all()
        );
        $this->assertSame('10', (string) $audit['summaryByTechnician']->sole()->total_commission);
        $this->assertSame('10', (string) $payment['summaries']->sole()->total_commission);
        $this->assertSame($technician->id, $payment['summaries']->sole()->technician_id);
    }

    public function test_payment_preview_and_confirmation_only_use_pending_commissions_from_active_sales(): void
    {
        [$technician, $activeCommission, $legacyPendingOnVoidedSale] = $this->records();
        $service = app(TechnicianPaymentService::class);

        $preview = $service->buildPreview([$technician->id]);

        $this->assertSame(1, $preview['total_items']);
        $this->assertEquals('10.00', $preview['total_amount']);
        $this->assertSame([$activeCommission->id], $preview['groups']->sole()['items']->pluck('id')->all());

        $batch = $service->confirm([$technician->id], '2026-07-18', null, null);

        $this->assertSame(1, $batch->total_items);
        $this->assertEquals('10.00', $batch->total_amount);
        $this->assertSame('paid', $activeCommission->fresh()->status);
        $this->assertSame('pending', $legacyPendingOnVoidedSale->fresh()->status);
    }

    public function test_direct_payment_update_does_not_pay_pending_commission_for_voided_sale(): void
    {
        [$technician, $activeCommission, $legacyPendingOnVoidedSale] = $this->records();

        app(TechnicianPaymentController::class)->pay(Request::create('/', 'POST', [
            'technician_id' => $technician->id,
            'month' => '2026-07',
        ]));

        $this->assertSame('paid', $activeCommission->fresh()->status);
        $this->assertSame('pending', $legacyPendingOnVoidedSale->fresh()->status);
    }

    public function test_batch_confirmation_rechecks_active_sale_at_the_payment_write(): void
    {
        [$technician, $activeCommission] = $this->records();
        $sale = $activeCommission->sale;
        $voidOnce = true;

        TechnicianCommission::retrieved(function () use (&$voidOnce, $sale): void {
            if ($voidOnce) {
                $voidOnce = false;
                $sale->update(['status' => Sale::STATUS_VOIDED]);
            }
        });

        try {
            app(TechnicianPaymentService::class)->confirm([$technician->id], '2026-07-18', null, null);
            $this->fail('Confirmation should reject a Sale voided after candidate selection.');
        } catch (\Exception) {
            $this->assertSame('pending', $activeCommission->fresh()->status);
            $this->assertSame(0, TechnicianPaymentBatch::query()->count());
        }
    }

    private function records(): array
    {
        $technician = Technician::query()->create(['name' => 'Test technician', 'active' => true]);
        $activeSale = $this->sale('SAL-COM-ACTIVE', Sale::STATUS_ACTIVE, $technician->id);
        $voidedSale = $this->sale('SAL-COM-VOIDED', Sale::STATUS_VOIDED, $technician->id);

        return [
            $technician,
            $this->commission($activeSale, $technician, 'pending', '10.00'),
            $this->commission($voidedSale, $technician, 'pending', '20.00'),
            $this->commission($voidedSale, $technician, 'voided', '30.00'),
            $this->commission($activeSale, $technician, 'paid', '40.00'),
        ];
    }

    private function sale(string $saleNo, string $status, int $technicianId): Sale
    {
        return Sale::query()->create([
            'sale_no' => $saleNo,
            'technician_id' => $technicianId,
            'sale_date' => '2026-07-18',
            'total_amount' => '100.00',
            'delivery_fee' => '0.00',
            'discount' => '0.00',
            'delivery_type' => 'pickup',
            'status' => $status,
        ]);
    }

    private function commission(Sale $sale, Technician $technician, string $status, string $amount): TechnicianCommission
    {
        return TechnicianCommission::query()->create([
            'sale_id' => $sale->id,
            'technician_id' => $technician->id,
            'commission_date' => '2026-07-18',
            'sale_total' => '100.00',
            'commission_rate' => '10.00',
            'commission_amount' => $amount,
            'status' => $status,
        ]);
    }
}
