<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Customer extends Model
{
    protected $fillable = [
        'code',
        'name',
        'phone',
        'address',
        'tax_number',
        'branch_type',
        'branch_number',
        'remark',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function deliveryAddresses(): HasMany
    {
        return $this->hasMany(CustomerDeliveryAddress::class);
    }

    public function defaultDeliveryAddress(): HasOne
    {
        return $this->hasOne(CustomerDeliveryAddress::class)
            ->where('is_default', true);
    }

    public function primaryDeliveryAddress(): HasOne
    {
        return $this->defaultDeliveryAddress();
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
