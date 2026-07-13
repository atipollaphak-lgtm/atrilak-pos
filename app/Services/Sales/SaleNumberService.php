<?php

namespace App\Services\Sales;

use App\Models\Sale;

class SaleNumberService
{
    public function generate(string $saleDate): string
    {
        $today = date('Ymd', strtotime($saleDate));

        $lastSale = Sale::whereDate('sale_date', $saleDate)
            ->latest('id')
            ->first();

        $runningNumber = $lastSale
            ? ((int) substr($lastSale->sale_no, -4)) + 1
            : 1;

        return 'SAL-' . $today . '-' . str_pad($runningNumber, 4, '0', STR_PAD_LEFT);
    }
}
