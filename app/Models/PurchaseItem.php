<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseItem extends Model
{
    protected $fillable = [
        'purchase_id',
        'product_id',
        'qty',
        'cost_price',
        'total',
        'product_unit_id',
        'conversion_rate_used',
        'base_qty',
        'unit_name_snapshot',
        'unit_code_snapshot',
        'average_cost_before',
        'average_cost_after',
        'stock_before',
        'stock_after',
        'stock_movement_id',
    ];

    protected $casts = [
        'qty' => 'decimal:4',
        'cost_price' => 'decimal:2',
        'total' => 'decimal:2',
        'conversion_rate_used' => 'decimal:4',
        'base_qty' => 'decimal:4',
        'average_cost_before' => 'decimal:2',
        'average_cost_after' => 'decimal:2',
        'stock_before' => 'decimal:4',
        'stock_after' => 'decimal:4',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    public function productUnit()
    {
        return $this->belongsTo(ProductUnit::class);
    }

    public function stockMovement()
    {
        return $this->belongsTo(StockMovement::class);
    }
}
