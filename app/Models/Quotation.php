<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quotation extends Model
{
    protected $fillable = [
        'quotation_no',
        'customer_id',
        'quotation_date',
        'total_amount',
        'remark',
        'status',
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
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function convertedSale()
    {
        return $this->hasOne(Sale::class);
    }
}
