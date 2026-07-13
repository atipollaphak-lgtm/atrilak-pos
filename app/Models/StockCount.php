<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockCount extends Model
{
    protected $fillable = [
        'count_no',
        'count_date',
        'remark',
    ];

    public function items()
    {
        return $this->hasMany(StockCountItem::class);
    }
}
