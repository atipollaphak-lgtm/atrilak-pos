<?php

namespace App\Data\Products;

final readonly class ProductImportResultData
{
    public function __construct(
        public int $productCount,
        public int $stockMovementCount,
        public ?string $firstProductCode = null,
        public ?string $lastProductCode = null,
        public ?string $importReference = null,
    ) {}

    public function toArray(): array
    {
        return [
            'product_count' => $this->productCount,
            'stock_movement_count' => $this->stockMovementCount,
            'first_product_code' => $this->firstProductCode,
            'last_product_code' => $this->lastProductCode,
            'import_reference' => $this->importReference,
        ];
    }
}
