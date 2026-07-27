<?php

namespace App\Services;

use App\Models\TechnicianCommission;
use App\Models\TechnicianPaymentBatch;
use Illuminate\Support\Facades\DB;

class TechnicianPaymentService
{
    public function buildPreview(array $technicianIds): array
    {
        $commissions = TechnicianCommission::with(['technician', 'sale'])
            ->whereIn('technician_id', $technicianIds)
            ->where('status', 'pending')
            ->whereHas('sale', fn ($query) => $query->active())
            ->orderBy('technician_id')
            ->orderBy('id')
            ->get();

        $groups = $commissions
            ->groupBy('technician_id')
            ->map(function ($items) {
                return [
                    'technician' => $items->first()->technician,
                    'items' => $items,
                    'total_items' => $items->count(),
                    'total_amount' => $items->sum('commission_amount'),
                ];
            })
            ->values();

        return [
            'groups' => $groups,
            'total_technicians' => $groups->count(),
            'total_items' => $commissions->count(),
            'total_amount' => $commissions->sum('commission_amount'),
        ];
    }

    public function confirm(array $technicianIds, string $paymentDate, ?string $remark, ?int $userId): TechnicianPaymentBatch
    {
        return DB::transaction(function () use ($technicianIds, $paymentDate, $remark, $userId) {

            $commissions = TechnicianCommission::whereIn('technician_id', $technicianIds)
                ->where('status', 'pending')
                ->whereHas('sale', fn ($query) => $query->active())
                ->lockForUpdate()
                ->get();

            if ($commissions->count() === 0) {
                throw new \Exception('ไม่พบรายการค่าช่างค้างจ่าย');
            }

            $batchNo = $this->generateBatchNo($paymentDate);

            $batch = new TechnicianPaymentBatch();
            $batch->batch_no = $batchNo;
            $batch->payment_date = $paymentDate;
            $batch->total_technicians = $commissions->pluck('technician_id')->unique()->count();
            $batch->total_items = $commissions->count();
            $batch->total_amount = $commissions->sum('commission_amount');
            $batch->status = 'confirmed';
            $batch->remark = $remark;
            $batch->created_by = $userId;
            $batch->approved_by = $userId;

            if (isset($batch->technician_id)) {
                $batch->technician_id = null;
            }

            $batch->save();

            foreach ($commissions as $commission) {
                $paymentAttributes = [
                    'payment_batch_id' => $batch->id,
                    'status' => 'paid',
                    'paid_at' => now(),
                    'paid_by' => $userId,
                ];

                if (array_key_exists('payment_batch_no', $commission->getAttributes())) {
                    $paymentAttributes['payment_batch_no'] = $batchNo;
                }

                $updated = TechnicianCommission::query()
                    ->whereKey($commission->getKey())
                    ->where('status', 'pending')
                    ->whereHas('sale', fn ($query) => $query->active())
                    ->update($paymentAttributes);

                if ($updated !== 1) {
                    throw new \RuntimeException('Commission is no longer payable.');
                }
            }

            return $batch;
        });
    }

    private function generateBatchNo(string $paymentDate): string
    {
        $prefix = 'PAY-' . date('Ym', strtotime($paymentDate));

        $latest = TechnicianPaymentBatch::where('batch_no', 'like', $prefix . '-%')
            ->orderBy('batch_no', 'desc')
            ->first();

        if (!$latest) {
            return $prefix . '-0001';
        }

        $lastNumber = (int) substr($latest->batch_no, -4);

        return $prefix . '-' . str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
    }
}
