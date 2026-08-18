<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerImportRow extends Model
{
    protected $fillable = [
        'batch_id',
        'customer_id',
        'delivery_zone_id',
        'row_number',
        'external_id',
        'name',
        'phone',
        'tax_number',
        'branch_type',
        'branch_number',
        'address',
        'remark',
        'status',
        'reasons',
        'warnings',
        'original_values',
    ];

    protected $casts = [
        'row_number' => 'integer',
        'delivery_zone_id' => 'integer',
        'reasons' => 'array',
        'warnings' => 'array',
        'original_values' => 'array',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(CustomerImportBatch::class, 'batch_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function deliveryZone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class, 'delivery_zone_id');
    }
}
