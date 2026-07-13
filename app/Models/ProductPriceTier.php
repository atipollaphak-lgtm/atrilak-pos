<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductPriceTier extends Model
{
    protected $fillable = [
        'product_unit_id',
        'min_qty',
        'discount_percent',
        'fixed_price',
        'active',
        'sort_order',
    ];

    protected $casts = [
        'min_qty' => 'integer',
        'discount_percent' => 'decimal:2',
        'fixed_price' => 'decimal:2',
        'active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function productUnit()
    {
        return $this->belongsTo(ProductUnit::class);
    }
}
