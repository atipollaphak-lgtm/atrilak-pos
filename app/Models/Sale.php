<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    public bool $idempotentReplay = false;

    protected $fillable = [
        'sale_no',
        'idempotency_key',
        'idempotency_payload_hash',
        'customer_id',
        'customer_delivery_address_id',
        'technician_id',
        'sale_date',
        'total_amount',
        'delivery_fee',
        'delivery_type',
        'discount',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function customerDeliveryAddress()
    {
        return $this->belongsTo(CustomerDeliveryAddress::class);
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function technician()
    {
        return $this->belongsTo(Technician::class);
    }

    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }
}
