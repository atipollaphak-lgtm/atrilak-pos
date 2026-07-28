<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoryPricingRule extends Model
{
    protected $fillable = [
        'category_id',
        'pricing_method',
        'pricing_value',
        'rounding_direction',
        'rounding_unit',
        'active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'pricing_value' => 'decimal:2',
        'rounding_unit' => 'decimal:2',
        'active' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
