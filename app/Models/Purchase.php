<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    public const SOURCE_SUPPLIER = 'supplier';

    public const SOURCE_PRODUCTION = 'production';

    public const STATUS_POSTED = 'posted';

    protected $fillable = [
        'supplier_id',
        'purchase_date',
        'total_amount',
        'remark',
        'source',
        'supplier_document_number',
        'status',
        'created_by',
        'idempotency_key',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getDisplaySourceAttribute(): string
    {
        return $this->source ?: self::SOURCE_SUPPLIER;
    }

    public function getDisplayStatusAttribute(): string
    {
        return $this->status ?: self::STATUS_POSTED;
    }
}
