<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyPaymentClosing extends Model
{
    protected $fillable = [
        'business_date',
        'status',
        'expected_cash_amount',
        'expected_promptpay_amount',
        'expected_recorded_sales_amount',
        'expected_received_cash_amount',
        'expected_change_amount',
        'cash_sales_count',
        'promptpay_sales_count',
        'mixed_sales_count',
        'unrecorded_payment_count',
        'actual_cash_amount',
        'actual_promptpay_amount',
        'cash_variance',
        'promptpay_variance',
        'notes',
        'opened_by',
        'finalized_by',
        'finalized_at',
        'revision',
    ];

    protected function casts(): array
    {
        return [
            'finalized_at' => 'datetime',
            'revision' => 'integer',
            'cash_sales_count' => 'integer',
            'promptpay_sales_count' => 'integer',
            'mixed_sales_count' => 'integer',
            'unrecorded_payment_count' => 'integer',
            'expected_cash_amount' => 'decimal:2',
            'expected_promptpay_amount' => 'decimal:2',
            'expected_recorded_sales_amount' => 'decimal:2',
            'expected_received_cash_amount' => 'decimal:2',
            'expected_change_amount' => 'decimal:2',
            'actual_cash_amount' => 'decimal:2',
            'actual_promptpay_amount' => 'decimal:2',
            'cash_variance' => 'decimal:2',
            'promptpay_variance' => 'decimal:2',
        ];
    }

    public function sales()
    {
        return $this->hasMany(DailyPaymentClosingSale::class);
    }

    public function openedBy()
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function finalizedBy()
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }
}
