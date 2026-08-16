<?php

namespace App\Data\Customers;

final readonly class CustomerImportResultData
{
    public function __construct(
        public int $batchId,
        public string $status,
        public int $totalParsed,
        public int $importedCount,
        public int $duplicateCount,
        public int $reviewCount,
        public int $invalidCount,
        public int $notSelectedCount,
    ) {}

    public function toArray(): array
    {
        return [
            'batch_id' => $this->batchId,
            'status' => $this->status,
            'total_parsed' => $this->totalParsed,
            'imported_count' => $this->importedCount,
            'duplicate_count' => $this->duplicateCount,
            'review_count' => $this->reviewCount,
            'invalid_count' => $this->invalidCount,
            'not_selected_count' => $this->notSelectedCount,
        ];
    }
}
