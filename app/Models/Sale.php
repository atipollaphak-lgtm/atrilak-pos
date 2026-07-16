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
        'store_name_snapshot',
        'store_address_snapshot',
        'store_phone_snapshot',
        'store_tax_number_snapshot',
        'store_branch_type_snapshot',
        'store_branch_number_snapshot',
        'customer_name_snapshot',
        'customer_phone_snapshot',
        'customer_address_snapshot',
        'customer_tax_number_snapshot',
        'customer_branch_type_snapshot',
        'customer_branch_number_snapshot',
        'technician_name_snapshot',
        'delivery_address_name_snapshot',
        'delivery_receiver_name_snapshot',
        'delivery_receiver_phone_snapshot',
        'delivery_full_address_snapshot',
        'delivery_landmark_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'revision' => 'integer',
        ];
    }

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
