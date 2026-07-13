<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductScheduledPrice extends Model
{
    protected $fillable = [
        'product_id',
        'price',
        'start_at',
        'end_at',
        'status',
        'created_by',
        'remark',
        'applied_at',
    ];

    protected $casts = [
        'start_at'  => 'datetime',
        'end_at'    => 'datetime',
        'applied_at'=> 'datetime',
        'price'     => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
