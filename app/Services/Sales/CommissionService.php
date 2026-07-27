<?php

namespace App\Services\Sales;

use App\Models\Sale;
use App\Models\TechnicianCommission;
use App\Services\TechnicianCommissionService;
use DomainException;
use Illuminate\Support\Collection;

class CommissionService
{
    public function createFromSale(Sale $sale): void
    {
        app(TechnicianCommissionService::class)
            ->createFromSale($sale);
    }

    public function lockForSale(Sale $sale): Collection
    {
        return TechnicianCommission::query()
            ->where('sale_id', $sale->getKey())
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    public function assertCanChange(Collection $commissions, bool $affectsCommission): void
    {
        if (! $affectsCommission) {
            return;
        }

        if ($commissions->contains(fn (TechnicianCommission $commission): bool => $commission->status !== 'pending'
            || $commission->payment_batch_id !== null
            || $commission->paid_at !== null
        )) {
            throw new DomainException(
                'ไม่สามารถแก้ไขหรือลบใบขายนี้ได้ เนื่องจากค่าช่างถูกจ่ายหรือรวมอยู่ในชุดการจ่ายแล้ว'
            );
        }
    }

    public function voidPendingForSale(Collection $commissions): void
    {
        $this->assertCanChange($commissions, true);

        $commissions->each(function (TechnicianCommission $commission): void {
            $commission->status = 'voided';
            $commission->save();
        });
    }

    public function refreshPendingForSale(Sale $sale, Collection $commissions): void
    {
        if ($commissions->count() > 1) {
            throw new DomainException('พบข้อมูลค่าช่างของใบขายซ้ำ กรุณาตรวจสอบข้อมูลก่อนดำเนินการต่อ');
        }

        $attributes = app(TechnicianCommissionService::class)
            ->attributesFromSale($sale);
        $commission = $commissions->first();

        if ($attributes === null) {
            $commission?->delete();

            return;
        }

        if ($commission === null) {
            TechnicianCommission::create($attributes);

            return;
        }

        $commission->fill($attributes);
        $commission->save();
    }
}
