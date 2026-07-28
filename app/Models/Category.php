<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    public const ROUNDING_OVERRIDES = ['0.25', '0.50', '1.00', '5.00', '10.00'];

    protected $fillable = [
        'name',
        'description',
        'code_prefix',
        'barcode_prefix',
        'active',
        'rounding_override',
    ];

    protected $casts = [
        'active' => 'boolean',
        'rounding_override' => 'decimal:2',
    ];

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function categoryPricingRule()
    {
        return $this->hasOne(CategoryPricingRule::class)->where('active', true);
    }
}
