<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockCountItem extends Model
{
    protected $fillable = [
        'stock_count_id',
        'product_id',
        'system_qty',
        'actual_qty',
        'difference',
    ];

    protected $casts = [
        'system_qty' => 'decimal:4',
        'actual_qty' => 'decimal:4',
        'difference' => 'decimal:4',
    ];

    public function stockCount()
    {
        return $this->belongsTo(StockCount::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
