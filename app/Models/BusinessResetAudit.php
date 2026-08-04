<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessResetAudit extends Model
{
    protected $fillable = [
        'user_id',
        'database_name',
        'business_counts_before',
        'business_counts_after',
        'protected_counts_before',
        'protected_counts_after',
        'backup_file',
        'backup_sha256',
        'backup_bytes',
        'status',
        'error_code',
    ];

    protected function casts(): array
    {
        return [
            'business_counts_before' => 'array',
            'business_counts_after' => 'array',
            'protected_counts_before' => 'array',
            'protected_counts_after' => 'array',
            'backup_bytes' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
