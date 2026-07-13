<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PricingSetting extends Model
{
    protected $fillable = [
        'auto_pricing_enabled',

        'default_profit_percent',

        'default_satang_rounding_mode',

        'default_baht_rounding_mode',
    ];

    protected $casts = [
        'auto_pricing_enabled' => 'boolean',
        'default_profit_percent' => 'decimal:2',
    ];
}
