<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'category_id',
        'unit_id',
        'barcode',
        'name',
        'unit',
        'cost_price',
        'selling_price',
        'stock_qty',
        'minimum_stock',
        'vat_enabled',
        'active',
        'remark',
        'auto_price_enabled',
        'price_lock',
        'profit_percent',
        'satang_rounding_mode',
        'baht_rounding_mode',
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'stock_qty' => 'decimal:4',
        'minimum_stock' => 'decimal:4',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function unitRelation()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function priceHistories()
    {
        return $this->hasMany(
            ProductPriceHistory::class
        );
    }

    public function priceTiers()
    {
        return $this->hasMany(ProductPriceTier::class, 'product_unit_id', 'id');
    }

    public function productUnits()
    {
        return $this->hasMany(ProductUnit::class);
    }

    public function barcodes()
    {
        return $this->hasMany(ProductBarcode::class);
    }
}
