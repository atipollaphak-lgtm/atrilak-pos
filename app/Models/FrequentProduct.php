<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FrequentProduct extends Model
{
    protected $table = 'pos_v3_frequent_products';

    protected $fillable = [
        'product_id',
        'sort_order',
    ];

    protected $casts = [
        'product_id' => 'integer',
        'sort_order' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
