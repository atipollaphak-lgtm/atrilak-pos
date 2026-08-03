<?php

namespace App\Data\Receivings;

use App\Models\Product;
use App\Models\ProductUnit;

final readonly class ReceiveStockLineData
{
    public function __construct(
        public int $productId,
        public ?int $productUnitId,
        public string $productName,
        public ?string $productCode,
        public ?string $barcode,
        public string $unitName,
        public ?string $unitCode,
        public string $qty,
        public string $costPrice,
        public string $conversionRate,
        public string $baseQty,
        public string $baseCostPrice,
        public string $lineTotal,
        public string $stockBefore,
        public string $stockAfter,
        public string $averageCostBefore,
        public string $averageCostAfter,
    ) {}

    public static function fromModels(
        Product $product,
        ?ProductUnit $unit,
        string $qty,
        string $costPrice,
        string $baseQty,
        string $baseCostPrice,
        string $lineTotal,
        string $stockBefore,
        string $stockAfter,
        string $averageCostAfter
    ): self {
        $unitName = $unit?->unit?->name ?: $product->unit;
        $unitCode = $unit?->unit?->code;

        return new self(
            productId: (int) $product->id,
            productUnitId: $unit?->id,
            productName: (string) $product->name,
            productCode: $product->product_code,
            barcode: $product->barcode,
            unitName: (string) $unitName,
            unitCode: $unitCode,
            qty: $qty,
            costPrice: $costPrice,
            conversionRate: $unit ? (string) $unit->conversion_rate : '1.0000',
            baseQty: $baseQty,
            baseCostPrice: $baseCostPrice,
            lineTotal: $lineTotal,
            stockBefore: $stockBefore,
            stockAfter: $stockAfter,
            averageCostBefore: (string) $product->cost_price,
            averageCostAfter: $averageCostAfter,
        );
    }

    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'product_unit_id' => $this->productUnitId,
            'product_name' => $this->productName,
            'product_code' => $this->productCode,
            'barcode' => $this->barcode,
            'unit_name' => $this->unitName,
            'unit_code' => $this->unitCode,
            'qty' => $this->qty,
            'cost_price' => $this->costPrice,
            'conversion_rate' => $this->conversionRate,
            'base_qty' => $this->baseQty,
            'base_cost_price' => $this->baseCostPrice,
            'line_total' => $this->lineTotal,
            'stock_before' => $this->stockBefore,
            'stock_after' => $this->stockAfter,
            'average_cost_before' => $this->averageCostBefore,
            'average_cost_after' => $this->averageCostAfter,
        ];
    }
}
