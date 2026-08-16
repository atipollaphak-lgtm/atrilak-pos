<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerExternalReference extends Model
{
    protected $fillable = [
        'customer_id',
        'batch_id',
        'source_system',
        'external_id',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(CustomerImportBatch::class, 'batch_id');
    }
}
