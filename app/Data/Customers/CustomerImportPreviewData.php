<?php

namespace App\Data\Customers;

final readonly class CustomerImportPreviewData
{
    public function __construct(
        public string $token,
        public int $userId,
        public string $filename,
        public string $fileHash,
        public string $sourceSystem,
        public array $rows,
        public array $errors,
        public string $state = 'pending',
    ) {}

    public function isPending(): bool
    {
        return $this->state === 'pending';
    }

    public function readyRows(): array
    {
        return array_values(array_filter(
            $this->rows,
            static fn (array $row): bool => ($row['status'] ?? null) === 'ready',
        ));
    }

    public function counts(): array
    {
        $counts = array_fill_keys(['ready', 'review_required', 'duplicate', 'invalid'], 0);

        foreach ($this->rows as $row) {
            $status = $row['status'] ?? 'invalid';
            if (array_key_exists($status, $counts)) {
                $counts[$status]++;
            }
        }

        return $counts;
    }
}
