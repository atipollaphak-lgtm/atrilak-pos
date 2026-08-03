<?php

namespace App\Data\Receivings;

use App\Models\Purchase;

final readonly class ReceiveStockResultData
{
    public function __construct(public Purchase $purchase) {}
}
