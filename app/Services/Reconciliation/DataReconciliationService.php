<?php

namespace App\Services\Reconciliation;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use JsonException;

class DataReconciliationService
{
    public function reconcile(?int $saleId = null, ?int $productId = null): array
    {
        return DB::transaction(function () use ($saleId, $productId): array {
            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
            }

            return $this->buildReport($saleId, $productId);
        });
    }

    private function buildReport(?int $saleId, ?int $productId): array
    {
        $confirmed = [];
        $warnings = [];
        $informational = [];

        $sales = DB::table('sales')
            ->select([
                'sales.id',
                'sales.sale_no',
                'sales.sale_date',
                'sales.technician_id',
                'sales.total_amount',
                'sales.delivery_fee',
                'sales.discount',
            ])
            ->when($saleId !== null, fn ($query) => $query->where('sales.id', $saleId))
            ->when($productId !== null, function ($query) use ($productId) {
                $query->whereExists(function ($subQuery) use ($productId) {
                    $subQuery->selectRaw('1')
                        ->from('sale_items as filtered_sale_items')
                        ->whereColumn('filtered_sale_items.sale_id', 'sales.id')
                        ->where('filtered_sale_items.product_id', $productId);
                });
            })
            ->orderBy('sales.id')
            ->get();

        $saleIds = $sales->pluck('id');
        $saleItems = $saleIds->isEmpty()
            ? collect()
            : DB::table('sale_items')
                ->select([
                    'id',
                    'sale_id',
                    'product_id',
                    'qty',
                    'selling_price',
                    'total',
                ])
                ->whereIn('sale_id', $saleIds)
                ->orderBy('sale_id')
                ->orderBy('id')
                ->get();
        $itemsBySale = $saleItems->groupBy('sale_id');

        foreach ($sales as $sale) {
            $items = $itemsBySale->get($sale->id, collect());
            $itemCount = $items->count();

            if ($itemCount === 0) {
                $confirmed[] = $this->finding(
                    'SALE_WITHOUT_ITEMS',
                    'sale',
                    $sale->id,
                    $sale->sale_no,
                    0,
                    'at least 1',
                    null,
                    ['sale_item_count' => 0]
                );
            }

            if ($sale->delivery_fee === null || $sale->discount === null) {
                $warnings[] = $this->finding(
                    'SALE_NULL_FINANCIAL_COMPONENT',
                    'sale',
                    $sale->id,
                    $sale->sale_no,
                    [
                        'delivery_fee' => $sale->delivery_fee,
                        'discount' => $sale->discount,
                    ],
                    'non-null values; NULL is treated as 0 for reconciliation',
                    null,
                    ['sale_item_count' => $itemCount]
                );
            }

            $itemsTotalCents = 0;

            foreach ($items as $item) {
                $itemsTotalCents += $this->scaledInteger($item->total, 2);
                $expectedLineCents = $this->scaledInteger(
                    (float) $item->qty * (float) $item->selling_price,
                    2
                );
                $actualLineCents = $this->scaledInteger($item->total, 2);

                if ($actualLineCents !== $expectedLineCents) {
                    $confirmed[] = $this->finding(
                        'SALE_ITEM_TOTAL_MISMATCH',
                        'sale_item',
                        $item->id,
                        $sale->sale_no,
                        $this->scaledString($actualLineCents, 2),
                        $this->scaledString($expectedLineCents, 2),
                        $this->scaledString($actualLineCents - $expectedLineCents, 2),
                        [
                            'sale_id' => $sale->id,
                            'product_id' => $item->product_id,
                            'qty' => (string) $item->qty,
                            'selling_price' => $this->decimalString($item->selling_price, 2),
                        ]
                    );
                }
            }

            $deliveryFeeCents = $this->scaledInteger($sale->delivery_fee ?? 0, 2);
            $discountCents = $this->scaledInteger($sale->discount ?? 0, 2);
            $expectedTotalCents = $itemsTotalCents + $deliveryFeeCents - $discountCents;
            $actualTotalCents = $this->scaledInteger($sale->total_amount, 2);

            if ($actualTotalCents !== $expectedTotalCents) {
                $confirmed[] = $this->finding(
                    'SALE_TOTAL_MISMATCH',
                    'sale',
                    $sale->id,
                    $sale->sale_no,
                    $this->scaledString($actualTotalCents, 2),
                    $this->scaledString($expectedTotalCents, 2),
                    $this->scaledString($actualTotalCents - $expectedTotalCents, 2),
                    [
                        'sale_item_count' => $itemCount,
                        'items_total' => $this->scaledString($itemsTotalCents, 2),
                        'delivery_fee' => $this->scaledString($deliveryFeeCents, 2),
                        'discount' => $this->scaledString($discountCents, 2),
                    ]
                );
            }
        }

        $productsQuery = DB::table('products')
            ->select(['id', 'name', 'stock_qty'])
            ->orderBy('id');

        if ($productId !== null) {
            $productsQuery->where('id', $productId);
        } elseif ($saleId !== null) {
            $productsQuery->whereIn('id', $saleItems->pluck('product_id')->unique());
        }

        $products = $productsQuery->get();
        $productIds = $products->pluck('id');
        $movements = $productIds->isEmpty()
            ? collect()
            : DB::table('stock_movements')
                ->select([
                    'id',
                    'product_id',
                    'type',
                    'qty',
                    'stock_before',
                    'stock_after',
                    'reference_type',
                    'reference_id',
                    'created_at',
                ])
                ->whereIn('product_id', $productIds)
                ->orderBy('product_id')
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();
        $movementsByProduct = $movements->groupBy('product_id');

        foreach ($products as $product) {
            $productMovements = $movementsByProduct->get($product->id, collect())->values();

            if ($productMovements->isEmpty()) {
                $informational[] = $this->finding(
                    'NO_MOVEMENT_HISTORY',
                    'product',
                    $product->id,
                    null,
                    $this->decimalString($product->stock_qty, 4),
                    null,
                    null,
                    ['product_name' => $product->name]
                );

                continue;
            }

            foreach ($productMovements->groupBy(fn ($movement) => (string) $movement->created_at) as $timestamp => $tied) {
                if ($tied->count() > 1) {
                    $informational[] = $this->finding(
                        'MOVEMENT_TIMESTAMP_TIE',
                        'product',
                        $product->id,
                        null,
                        $tied->count(),
                        'ID is used as the secondary ordering key',
                        null,
                        [
                            'product_name' => $product->name,
                            'created_at' => $timestamp,
                            'movement_ids' => $tied->pluck('id')->values()->all(),
                        ]
                    );
                }
            }

            $previous = null;

            foreach ($productMovements as $movement) {
                if ($previous !== null) {
                    $expectedBefore = $this->scaledInteger($previous->stock_after, 4);
                    $actualBefore = $this->scaledInteger($movement->stock_before, 4);

                    if ($actualBefore !== $expectedBefore) {
                        $confirmed[] = $this->finding(
                            'STOCK_MOVEMENT_CHAIN_BREAK',
                            'stock_movement',
                            $movement->id,
                            null,
                            $this->scaledString($actualBefore, 4),
                            $this->scaledString($expectedBefore, 4),
                            $this->scaledString($actualBefore - $expectedBefore, 4),
                            [
                                'product_id' => $product->id,
                                'product_name' => $product->name,
                                'previous_movement_id' => $previous->id,
                                'previous_stock_after' => $this->decimalString($previous->stock_after, 4),
                                'reference_type' => $movement->reference_type,
                                'reference_id' => $movement->reference_id,
                            ]
                        );
                    }
                }

                $previous = $movement;
            }

            $latest = $productMovements->last();
            $actualStock = $this->scaledInteger($product->stock_qty, 4);
            $expectedStock = $this->scaledInteger($latest->stock_after, 4);

            if ($actualStock !== $expectedStock) {
                $confirmed[] = $this->finding(
                    'PRODUCT_STOCK_MISMATCH',
                    'product',
                    $product->id,
                    null,
                    $this->scaledString($actualStock, 4),
                    $this->scaledString($expectedStock, 4),
                    $this->scaledString($actualStock - $expectedStock, 4),
                    [
                        'product_name' => $product->name,
                        'latest_movement_id' => $latest->id,
                        'reference_type' => $latest->reference_type,
                        'reference_id' => $latest->reference_id,
                    ]
                );
            }
        }

        $salesById = $sales->keyBy('id');
        $commissionQuery = DB::table('technician_commissions')
            ->select([
                'id',
                'sale_id',
                'technician_id',
                'commission_date',
                'sale_total',
                'commission_amount',
                'calculation_detail',
            ])
            ->orderBy('id');

        if ($saleId !== null || $productId !== null) {
            $commissionQuery->whereIn('sale_id', $saleIds);
        }

        $commissions = $commissionQuery->get();
        $commissionsBySale = $commissions->groupBy('sale_id');

        foreach ($commissionsBySale as $commissionSaleId => $saleCommissions) {
            if ($saleCommissions->count() > 1) {
                $sale = $salesById->get((int) $commissionSaleId);
                $confirmed[] = $this->finding(
                    'COMMISSION_DUPLICATE',
                    'sale',
                    (int) $commissionSaleId,
                    $sale?->sale_no,
                    $saleCommissions->count(),
                    1,
                    $saleCommissions->count() - 1,
                    ['commission_ids' => $saleCommissions->pluck('id')->values()->all()]
                );
            }
        }

        foreach ($commissions as $commission) {
            $sale = $salesById->get($commission->sale_id);

            if ($sale === null) {
                $confirmed[] = $this->finding(
                    'COMMISSION_ORPHAN_SALE',
                    'commission',
                    $commission->id,
                    null,
                    $commission->sale_id,
                    'existing sale ID',
                    null
                );

                continue;
            }

            if ((int) $commission->technician_id !== (int) $sale->technician_id) {
                $confirmed[] = $this->finding(
                    'COMMISSION_TECHNICIAN_MISMATCH',
                    'commission',
                    $commission->id,
                    $sale->sale_no,
                    $commission->technician_id,
                    $sale->technician_id,
                    null,
                    ['sale_id' => $sale->id]
                );
            }

            $commissionSaleTotal = $this->scaledInteger($commission->sale_total, 2);
            $saleTotal = $this->scaledInteger($sale->total_amount, 2);

            if ($commissionSaleTotal !== $saleTotal) {
                $confirmed[] = $this->finding(
                    'COMMISSION_SALE_TOTAL_MISMATCH',
                    'commission',
                    $commission->id,
                    $sale->sale_no,
                    $this->scaledString($commissionSaleTotal, 2),
                    $this->scaledString($saleTotal, 2),
                    $this->scaledString($commissionSaleTotal - $saleTotal, 2),
                    ['sale_id' => $sale->id]
                );
            }

            if (substr((string) $commission->commission_date, 0, 10) !== substr((string) $sale->sale_date, 0, 10)) {
                $confirmed[] = $this->finding(
                    'COMMISSION_DATE_MISMATCH',
                    'commission',
                    $commission->id,
                    $sale->sale_no,
                    substr((string) $commission->commission_date, 0, 10),
                    substr((string) $sale->sale_date, 0, 10),
                    null,
                    ['sale_id' => $sale->id]
                );
            }

            $detail = $this->parseCalculationDetail($commission->calculation_detail);

            if (! $detail['supported']) {
                $warnings[] = $this->finding(
                    'UNSUPPORTED_CALCULATION_DETAIL_FORMAT',
                    'commission',
                    $commission->id,
                    $sale->sale_no,
                    $detail['reason'],
                    'supported calculation_detail array format',
                    null,
                    ['sale_id' => $sale->id]
                );

                continue;
            }

            $commissionAmount = $this->scaledInteger($commission->commission_amount, 2);
            $detailAmount = $detail['commission_cents'];

            if ($commissionAmount !== $detailAmount) {
                $confirmed[] = $this->finding(
                    'COMMISSION_AMOUNT_DETAIL_MISMATCH',
                    'commission',
                    $commission->id,
                    $sale->sale_no,
                    $this->scaledString($commissionAmount, 2),
                    $this->scaledString($detailAmount, 2),
                    $this->scaledString($commissionAmount - $detailAmount, 2),
                    ['sale_id' => $sale->id]
                );
            }
        }

        $rules = DB::table('technician_commission_rules')
            ->where('active', true)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
        $productRules = $rules->whereNotNull('product_id')->groupBy('product_id');
        $categoryRules = $rules->whereNull('product_id')->whereNotNull('category_id')->groupBy('category_id');
        $itemProducts = $saleItems->pluck('product_id')->unique();
        $productsForCommission = $itemProducts->isEmpty()
            ? collect()
            : DB::table('products')
                ->select(['id', 'category_id'])
                ->whereIn('id', $itemProducts)
                ->get()
                ->keyBy('id');

        foreach ($sales as $sale) {
            if ($sale->technician_id === null || $commissionsBySale->has($sale->id)) {
                continue;
            }

            $expected = $this->expectedCommissionFromCurrentRules(
                $itemsBySale->get($sale->id, collect()),
                $productsForCommission,
                $productRules,
                $categoryRules
            );

            if ($expected['commission_cents'] <= 0) {
                continue;
            }

            $warnings[] = $this->finding(
                'COMMISSION_EXPECTED_FROM_CURRENT_RULES',
                'sale',
                $sale->id,
                $sale->sale_no,
                'no commission record',
                $this->scaledString($expected['commission_cents'], 2),
                $this->scaledString(-$expected['commission_cents'], 2),
                [
                    'technician_id' => $sale->technician_id,
                    'rule_ids' => $expected['rule_ids'],
                    'basis' => 'current active rules; historical rule state is unavailable',
                ]
            );
        }

        $this->sortFindings($confirmed);
        $this->sortFindings($warnings);
        $this->sortFindings($informational);

        return [
            'filters' => [
                'sale_id' => $saleId,
                'product_id' => $productId,
            ],
            'confirmed_anomalies' => $confirmed,
            'warnings' => $warnings,
            'informational_findings' => $informational,
            'summary' => [
                'confirmed_anomalies' => count($confirmed),
                'warnings' => count($warnings),
                'informational_findings' => count($informational),
                'checked' => [
                    'sales' => $sales->count(),
                    'sale_items' => $saleItems->count(),
                    'products' => $products->count(),
                    'stock_movements' => $movements->count(),
                    'commissions' => $commissions->count(),
                ],
            ],
        ];
    }

    private function expectedCommissionFromCurrentRules(
        Collection $items,
        Collection $products,
        Collection $productRules,
        Collection $categoryRules
    ): array {
        $commissionCents = 0;
        $ruleIds = [];

        foreach ($items as $item) {
            $product = $products->get($item->product_id);

            if ($product === null) {
                continue;
            }

            $rule = $productRules->get($product->id)?->first()
                ?? $categoryRules->get($product->category_id)?->first();

            if ($rule === null) {
                continue;
            }

            $lineCommission = match ($rule->rule_type) {
                'percent' => round((float) $item->total * ((float) $rule->rule_value / 100), 2),
                'amount' => round((float) $item->qty * (float) $rule->rule_value, 2),
                default => 0,
            };

            if ($lineCommission <= 0) {
                continue;
            }

            $commissionCents += $this->scaledInteger($lineCommission, 2);
            $ruleIds[] = $rule->id;
        }

        return [
            'commission_cents' => $commissionCents,
            'rule_ids' => array_values(array_unique($ruleIds)),
        ];
    }

    private function parseCalculationDetail(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return ['supported' => false, 'reason' => 'missing calculation_detail'];
        }

        try {
            $decoded = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return ['supported' => false, 'reason' => 'malformed JSON'];
        }

        if (! is_array($decoded) || ! array_is_list($decoded)) {
            return ['supported' => false, 'reason' => 'root value is not an array'];
        }

        $requiredKeys = [
            'product_name',
            'qty',
            'line_total',
            'rule_name',
            'rule_type',
            'rule_value',
            'commission',
        ];
        $commissionCents = 0;

        foreach ($decoded as $item) {
            if (! is_array($item) || array_diff($requiredKeys, array_keys($item)) !== []) {
                return ['supported' => false, 'reason' => 'array item has unsupported keys'];
            }

            if (! is_string($item['product_name'])
                || ! is_numeric($item['qty'])
                || ! is_numeric($item['line_total'])
                || ! is_string($item['rule_name'])
                || ! in_array($item['rule_type'], ['percent', 'amount'], true)
                || ! is_numeric($item['rule_value'])
                || ! is_numeric($item['commission'])) {
                return ['supported' => false, 'reason' => 'array item has unsupported value types'];
            }

            $commissionCents += $this->scaledInteger($item['commission'], 2);
        }

        return [
            'supported' => true,
            'commission_cents' => $commissionCents,
        ];
    }

    private function finding(
        string $code,
        string $entityType,
        int $entityId,
        ?string $documentNo,
        mixed $actual,
        mixed $expected,
        mixed $difference,
        array $details = []
    ): array {
        return [
            'code' => $code,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'document_no' => $documentNo,
            'actual' => $actual,
            'expected' => $expected,
            'difference' => $difference,
            'details' => $details,
        ];
    }

    private function scaledInteger(mixed $value, int $scale): int
    {
        return (int) round((float) $value * (10 ** $scale));
    }

    private function scaledString(int $value, int $scale): string
    {
        return number_format($value / (10 ** $scale), $scale, '.', '');
    }

    private function decimalString(mixed $value, int $scale): string
    {
        return number_format((float) $value, $scale, '.', '');
    }

    private function sortFindings(array &$findings): void
    {
        usort($findings, function (array $left, array $right): int {
            return [$left['code'], $left['entity_type'], $left['entity_id']]
                <=> [$right['code'], $right['entity_type'], $right['entity_id']];
        });
    }
}
