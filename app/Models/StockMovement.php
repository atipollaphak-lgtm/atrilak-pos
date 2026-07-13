<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = [
        'product_id',
        'type',
        'qty',
        'stock_before',
        'stock_after',
        'reference_type',
        'reference_id',
        'remark',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
