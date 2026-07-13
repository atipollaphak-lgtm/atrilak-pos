<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
    public function deliveryAddresses(): HasMany
    {
        return $this->hasMany(CustomerDeliveryAddress::class);
    }

    public function defaultDeliveryAddress()
    {
        return $this->hasOne(CustomerDeliveryAddress::class)
            ->where('is_default', true);
    }
}
