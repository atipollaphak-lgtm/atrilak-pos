<?php

namespace App\Data\Products;

final readonly class ProductImportRowData
{
    public function __construct(
        public int $rowNumber,
        public array $values,
        public array $originalValues,
        public array $errors = [],
    ) {}

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    public function toArray(): array
    {
        return [
            'row_number' => $this->rowNumber,
            'values' => $this->values,
            'original_values' => $this->originalValues,
            'errors' => $this->errors,
        ];
    }
}
