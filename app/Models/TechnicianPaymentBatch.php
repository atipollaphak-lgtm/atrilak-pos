<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TechnicianPaymentBatch extends Model
{
    protected $fillable = [
        'batch_no',
        'payment_date',
        'total_technicians',
        'total_items',
        'total_amount',
        'status',
        'remark',
        'created_by',
        'approved_by',
    ];

    public function commissions()
    {
        return $this->hasMany(TechnicianCommission::class, 'payment_batch_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

}
