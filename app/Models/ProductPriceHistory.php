<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductPriceHistory extends Model
{
    protected $fillable = [
        'product_id',
        'old_price',
        'new_price',
        'average_cost',
        'profit_percent',
        'price_before_round',
        'satang_rounded_price',
        'final_price',
        'created_from',
        'user_id',
        'remark',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
