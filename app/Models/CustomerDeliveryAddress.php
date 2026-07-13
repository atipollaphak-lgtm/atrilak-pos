<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerDeliveryAddress extends Model
{
    protected $fillable = [
        'customer_id',
        'name',
        'receiver_name',
        'receiver_phone',
        'address',
        'landmark',
        'delivery_zone_id',
        'latitude',
        'longitude',
        'is_default',
        'remark',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function deliveryZone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class);
    }
}
