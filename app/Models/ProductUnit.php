<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductUnit extends Model
{
    protected $fillable = [
        'product_id',
        'unit_id',
        'conversion_rate',
        'is_base_unit',
        'is_purchase_unit',
        'is_sale_unit',
        'purchase_price',
        'selling_price',
        'active',
        'sort_order',
    ];

    protected $casts = [
        'conversion_rate' => 'decimal:4',
        'purchase_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'is_base_unit' => 'boolean',
        'is_purchase_unit' => 'boolean',
        'is_sale_unit' => 'boolean',
        'active' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function barcodes()
    {
        return $this->hasMany(ProductBarcode::class);
    }

    public function priceTiers()
    {
        return $this->hasMany(ProductPriceTier::class);
    }

    public function promotions()
    {
        return $this->hasMany(ProductUnitPromotion::class);
    }
}
