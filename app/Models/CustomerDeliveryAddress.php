<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerDeliveryAddress extends Model
{
    protected $casts = [
        'is_default' => 'boolean',
    ];

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

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
