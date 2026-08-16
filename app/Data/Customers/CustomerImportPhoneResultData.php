<?php

namespace App\Data\Customers;

final readonly class CustomerImportPhoneResultData
{
    public function __construct(
        public ?string $phone,
        public string $status,
        public array $warnings = [],
    ) {}

    public function requiresReview(): bool
    {
        return $this->status === 'review_required';
    }
}
