<?php

namespace App\ValueObjects\Sales;

final readonly class ResolvedSaleLine
{
    public const SOURCE_LEGACY_FACTOR_ONE = 'legacy_factor_one';

    public const SOURCE_CURRENT_PRODUCT_UNIT = 'current_product_unit';

    public const SOURCE_STORED_SNAPSHOT = 'stored_snapshot';

    public function __construct(
        public int $originalIndex,
        public int $productId,
        public ?int $productUnitId,
        public string $saleQty,
        public string $sellingPrice,
        public string $conversionRateUsed,
        public string $baseQty,
        public ?string $unitNameSnapshot,
        public ?string $unitCodeSnapshot,
        public string $resolutionSource,
        private array $sourceLine = [],
    ) {}

    public function toArray(bool $includeUnitSnapshots = true): array
    {
        $resolved = [
            'product_id' => $this->productId,
            'product_unit_id' => $this->productUnitId,
            'qty' => $this->saleQty,
            'selling_price' => $this->sellingPrice,
            'conversion_rate_used' => $this->conversionRateUsed,
            'base_qty' => $this->baseQty,
        ];

        if ($includeUnitSnapshots) {
            $resolved['unit_name_snapshot'] = $this->unitNameSnapshot;
            $resolved['unit_code_snapshot'] = $this->unitCodeSnapshot;
        }

        return array_merge($this->sourceLine, $resolved);
    }
}
