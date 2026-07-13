<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductUnitPromotion extends Model
{
    protected $fillable = [
        'product_unit_id',
        'name',
        'min_qty',
        'discount_percent',
        'discount_amount',
        'start_date',
        'end_date',
        'active',
        'sort_order',
    ];

    protected $casts = [
        'min_qty' => 'integer',
        'discount_percent' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function productUnit()
    {
        return $this->belongsTo(ProductUnit::class);
    }
}
