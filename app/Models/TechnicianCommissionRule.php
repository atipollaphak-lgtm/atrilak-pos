<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TechnicianCommissionRule extends Model
{
    protected $fillable = [
        'category_id',
        'product_id',
        'name',
        'rule_type',
        'rule_value',
        'active',
        'remark',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
