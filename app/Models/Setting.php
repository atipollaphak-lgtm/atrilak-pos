<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'store_name',
        'store_address',
        'store_phone',
        'tax_number',
        'branch_type',
        'branch_number',
        'logo_image',
        'qr_image',
        'receipt_footer',
    ];
}
