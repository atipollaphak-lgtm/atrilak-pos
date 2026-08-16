<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerImportBatch extends Model
{
    protected $fillable = [
        'source_system',
        'original_filename',
        'file_hash',
        'created_by',
        'total_rows',
        'ready_count',
        'review_count',
        'duplicate_count',
        'invalid_count',
        'selected_count',
        'imported_count',
        'counts',
        'status',
        'failure_reason',
        'confirmed_at',
    ];

    protected $casts = [
        'total_rows' => 'integer',
        'ready_count' => 'integer',
        'review_count' => 'integer',
        'duplicate_count' => 'integer',
        'invalid_count' => 'integer',
        'selected_count' => 'integer',
        'imported_count' => 'integer',
        'counts' => 'array',
        'confirmed_at' => 'datetime',
    ];

    public function rows(): HasMany
    {
        return $this->hasMany(CustomerImportRow::class, 'batch_id');
    }

    public function references(): HasMany
    {
        return $this->hasMany(CustomerExternalReference::class, 'batch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
