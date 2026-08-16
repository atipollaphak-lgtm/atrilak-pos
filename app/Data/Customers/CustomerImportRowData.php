<?php

namespace App\Data\Customers;

final readonly class CustomerImportRowData
{
    public function __construct(
        public int $rowNumber,
        public ?string $externalId,
        public string $name,
        public ?string $phone,
        public ?string $taxNumber,
        public string $branchType,
        public ?string $branchNumber,
        public ?string $address,
        public ?string $remark,
        public string $status,
        public array $reasons = [],
        public array $warnings = [],
        public array $originalValues = [],
    ) {}

    public function toArray(): array
    {
        return [
            'row_number' => $this->rowNumber,
            'external_id' => $this->externalId,
            'name' => $this->name,
            'phone' => $this->phone,
            'tax_number' => $this->taxNumber,
            'branch_type' => $this->branchType,
            'branch_number' => $this->branchNumber,
            'address' => $this->address,
            'remark' => $this->remark,
            'status' => $this->status,
            'reasons' => $this->reasons,
            'warnings' => $this->warnings,
            'original_values' => $this->originalValues,
        ];
    }
}
