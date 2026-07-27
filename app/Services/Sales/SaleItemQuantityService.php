<?php

namespace App\Services\Sales;

use App\Models\SaleItem;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DomainException;
use Illuminate\Support\Collection;

class SaleItemQuantityService
{
    public function assertAuthoritativeQuantities(Collection $items): void
    {
        $items->each(fn (SaleItem $item) => $this->authoritativeBaseQuantity($item));
    }

    public function authoritativeBaseQuantity(SaleItem $item): string
    {
        if ($item->base_qty !== null) {
            return $this->baseQuantity($item->base_qty);
        }

        if ($item->product_unit_id === null) {
            return $this->baseQuantity($item->qty);
        }

        throw new DomainException(
            'ไม่สามารถแก้ไขหรือลบใบขายนี้ได้ เนื่องจากรายการขายจากระบบเดิมไม่มีข้อมูลจำนวนหน่วยฐานที่ยืนยันได้'
        );
    }

    private function baseQuantity(mixed $quantity): string
    {
        return (string) BigDecimal::of((string) $quantity)
            ->toScale(4, RoundingMode::UNNECESSARY);
    }
}
