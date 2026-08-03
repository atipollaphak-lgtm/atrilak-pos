<?php

namespace App\Data\Products;

final readonly class ProductImportPreviewData
{
    public function __construct(
        public string $token,
        public int $userId,
        public string $filename,
        public string $fileHash,
        public array $rows,
        public array $errors,
        public string $state = 'pending',
    ) {}

    public function isValid(): bool
    {
        return $this->errors === [] && collect($this->rows)->every(
            static fn (array $row): bool => ($row['errors'] ?? []) === []
        );
    }

    public function toArray(): array
    {
        return [
            'token' => $this->token,
            'user_id' => $this->userId,
            'filename' => $this->filename,
            'file_hash' => $this->fileHash,
            'rows' => $this->rows,
            'errors' => $this->errors,
            'state' => $this->state,
        ];
    }
}
