<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    protected $fillable = [
    'sale_id',
    'product_id',
    'product_unit_id',
    'qty',
    'selling_price',
    'total',
    'cost_price',
    'profit',
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
