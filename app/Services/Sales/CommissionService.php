<?php

namespace App\Services\Sales;

use App\Models\Sale;
use App\Services\TechnicianCommissionService;

class CommissionService
{
    public function createFromSale(Sale $sale): void
    {
        app(TechnicianCommissionService::class)
            ->createFromSale($sale);
    }
}
