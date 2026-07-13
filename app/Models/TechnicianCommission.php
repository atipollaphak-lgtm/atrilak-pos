<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Technician;
use App\Models\Sale;

class TechnicianCommission extends Model
{
    protected $fillable = [
        'sale_id',
        'technician_id',

        'commission_date',
        'sale_total',
        'commission_rate',
        'commission_amount',

        'manual_adjust',
        'payable_amount',
        'adjust_remark',

        'rule_name',
        'calculation_detail',

        'status',

        'paid_at',
        'paid_by',
        'payment_batch_id',
        'remark',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function technician()
    {
        return $this->belongsTo(Technician::class);
    }

    public function paidBy()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
    public function paymentBatch()
    {
        return $this->belongsTo(TechnicianPaymentBatch::class, 'payment_batch_id');
    }
}
