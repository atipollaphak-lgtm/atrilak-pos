<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryZone extends Model
{
    use HasFactory;

    public const ROUNDING_INCREMENTS = ['0.25', '0.50', '1.00', '5.00', '10.00'];

    protected $fillable = [
        'name',
        'price_markup_percent',
        'rounding_increment',
        'sort_order',
        'base_delivery_fee',
        'free_delivery_min_amount',
        'minimum_profit',
        'active',
        'remark',
    ];

    protected $casts = [
        'price_markup_percent' => 'decimal:2',
        'rounding_increment' => 'decimal:2',
        'base_delivery_fee' => 'decimal:2',
        'free_delivery_min_amount' => 'decimal:2',
        'active' => 'boolean',
    ];

    public function customerDeliveryAddresses(): HasMany
    {
        return $this->hasMany(CustomerDeliveryAddress::class);
    }
}
