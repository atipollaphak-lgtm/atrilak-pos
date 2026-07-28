<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = [
        'name',
        'code_prefix',
        'barcode_prefix',
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
