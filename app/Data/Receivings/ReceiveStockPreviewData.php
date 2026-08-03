<?php

namespace App\Data\Receivings;

final readonly class ReceiveStockPreviewData
{
    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function __construct(
        public string $source,
        public ?int $supplierId,
        public string $purchaseDate,
        public ?string $supplierDocumentNumber,
        public ?string $remark,
        public array $lines,
        public string $totalAmount,
    ) {}

    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'supplier_id' => $this->supplierId,
            'purchase_date' => $this->purchaseDate,
            'supplier_document_number' => $this->supplierDocumentNumber,
            'remark' => $this->remark,
            'lines' => $this->lines,
            'total_amount' => $this->totalAmount,
        ];
    }
}
