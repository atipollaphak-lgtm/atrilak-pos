<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HoldBillItem extends Model
{
    protected $fillable = [
        'hold_bill_id',
        'product_id',
        'product_unit_id',
        'product_unit_id_snapshot',
        'qty',
        'selling_price',
        'product_name_snapshot',
        'product_sku_snapshot',
        'product_code_snapshot',
        'unit_name_snapshot',
        'unit_code_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:2',
            'selling_price' => 'decimal:2',
        ];
    }

    public function holdBill(): BelongsTo
    {
        return $this->belongsTo(HoldBill::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class);
    }
}
