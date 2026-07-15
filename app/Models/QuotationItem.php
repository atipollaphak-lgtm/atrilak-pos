<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuotationItem extends Model
{
    protected $fillable = [
        'quotation_id',
        'product_id',
        'product_unit_id',
        'conversion_rate_used',
        'base_qty',
        'qty',
        'selling_price',
        'total',
        'product_name_snapshot',
        'product_sku_snapshot',
        'product_code_snapshot',
        'unit_name_snapshot',
        'unit_code_snapshot',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'conversion_rate_used' => 'decimal:4',
        'base_qty' => 'decimal:4',
        'selling_price' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
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
