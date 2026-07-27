<?php

namespace App\Services\Sales;

use App\Models\Sale;
use App\Models\SaleItem;

class SaleFinancialSnapshotService
{
    public function __construct(
        private readonly SaleDecimalService $decimalService
    ) {}

    public function itemRevenue(SaleItem $item): string
    {
        return $this->decimalService->money($item->total);
    }

    public function itemProfit(SaleItem $item): string
    {
        return $this->decimalService->money($item->profit);
    }

    public function itemCost(SaleItem $item): string
    {
        return $this->decimalService->storedLineCost(
            $this->itemRevenue($item),
            $this->itemProfit($item)
        );
    }

    public function saleSummary(Sale $sale): array
    {
        $items = $sale->relationLoaded('items')
            ? $sale->items
            : $sale->items()->get();

        return [
            'revenue' => $this->decimalService->sumMoney(
                $items->map(fn (SaleItem $item): string => $this->itemRevenue($item))
            ),
            'cost' => $this->decimalService->sumMoney(
                $items->map(fn (SaleItem $item): string => $this->itemCost($item))
            ),
            'profit' => $this->decimalService->sumMoney(
                $items->map(fn (SaleItem $item): string => $this->itemProfit($item))
            ),
        ];
    }

    public function summariesBySale(iterable $sales): array
    {
        $summaries = [];

        foreach ($sales as $sale) {
            $summaries[$sale->getKey()] = $this->saleSummary($sale);
        }

        return $summaries;
    }

    public function sumProfit(iterable $sales): string
    {
        return $this->decimalService->sumMoney(
            collect($sales)->map(
                fn (Sale $sale): string => $this->saleSummary($sale)['profit']
            )
        );
    }

    public function sumRevenue(iterable $sales): string
    {
        return $this->decimalService->sumMoney(
            collect($sales)->map(
                fn (Sale $sale): string => $this->saleSummary($sale)['revenue']
            )
        );
    }

    public function sumCost(iterable $sales): string
    {
        return $this->decimalService->sumMoney(
            collect($sales)->map(
                fn (Sale $sale): string => $this->saleSummary($sale)['cost']
            )
        );
    }
}
