<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductPriceHistory extends Model
{
    protected $fillable = [
        'product_id',
        'old_cost_price',
        'new_cost_price',
        'old_selling_price',
        'new_selling_price',
        'old_price',
        'new_price',
        'old_average_cost',
        'pricing_method',
        'pricing_source',
        'pricing_value',
        'category_pricing_rule_id',
        'category_id',
        'category_name_snapshot',
        'category_rule_value',
        'rounding_direction',
        'rounding_unit',
        'average_cost',
        'profit_percent',
        'price_before_round',
        'satang_rounded_price',
        'final_price',
        'profit_amount',
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
