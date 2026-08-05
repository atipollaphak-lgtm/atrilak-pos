<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HoldBill extends Model
{
    protected $fillable = [
        'hold_no',
        'user_id',
        'customer_id',
        'customer_delivery_address_id',
        'delivery_zone_id',
        'delivery_zone_name_snapshot',
        'delivery_zone_markup_percent_snapshot',
        'delivery_zone_rounding_increment_snapshot',
        'delivery_zone_minimum_profit_snapshot',
        'sale_date',
        'delivery_date',
        'delivery_type',
        'discount',
        'delivery_fee',
        'total_amount',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'sale_date' => 'date:Y-m-d',
            'delivery_date' => 'date:Y-m-d',
            'discount' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'delivery_zone_markup_percent_snapshot' => 'decimal:2',
            'delivery_zone_rounding_increment_snapshot' => 'decimal:2',
            'delivery_zone_minimum_profit_snapshot' => 'decimal:2',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(HoldBillItem::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function customerDeliveryAddress(): BelongsTo
    {
        return $this->belongsTo(CustomerDeliveryAddress::class);
    }

    public function deliveryZone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
