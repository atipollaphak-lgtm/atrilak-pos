<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyPaymentClosingSale extends Model
{
    protected $fillable = [
        'daily_payment_closing_id',
        'sale_id',
        'sale_revision',
        'sale_status',
        'sale_total_amount',
        'payment_method',
        'cash_amount',
        'promptpay_amount',
        'received_amount',
        'change_amount',
    ];

    protected function casts(): array
    {
        return [
            'sale_revision' => 'integer',
            'sale_total_amount' => 'decimal:2',
            'cash_amount' => 'decimal:2',
            'promptpay_amount' => 'decimal:2',
            'received_amount' => 'decimal:2',
            'change_amount' => 'decimal:2',
        ];
    }

    public function closing()
    {
        return $this->belongsTo(DailyPaymentClosing::class, 'daily_payment_closing_id');
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }
}
