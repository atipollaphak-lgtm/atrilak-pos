<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    protected $fillable = [
        'sale_id',
        'product_id',
        'product_unit_id',
        'conversion_rate_used',
        'base_qty',
        'qty',
        'selling_price',
        'total',
        'cost_price',
        'profit',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'conversion_rate_used' => 'decimal:4',
        'base_qty' => 'decimal:4',
        'selling_price' => 'decimal:2',
        'total' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'profit' => 'decimal:2',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function productUnit()
    {
        return $this->belongsTo(ProductUnit::class);
    }
}
