<?php

namespace App\Services\Receivings;

use App\Data\Receivings\ReceiveStockLineData;
use App\Data\Receivings\ReceiveStockPreviewData;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Services\Pricing\AverageCostService;
use App\Services\StockLockService;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DomainException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ReceiveStockService
{
    public function __construct(
        private ReceiveStockValidationService $validation,
        private ReceiveStockPreviewStorageService $previewStorage,
        private AverageCostService $averageCostService,
        private StockLockService $stockLockService,
    ) {}

    public function preview(int $userId, array $data): array
    {
        $normalized = $this->validation->normalize($data);
        $this->validation->assertSourceReferences($normalized);

        $productIds = array_column($normalized['items'], 'product_id');
        $products = Product::query()
            ->whereIn('id', $productIds)
            ->with('productUnits.unit')
            ->get()
            ->keyBy('id');
        $this->assertProductsExist($normalized['items'], $products);
        $resolvedItems = $this->validation->resolveItems($normalized['items'], $products);
        $preview = $this->buildPreview($normalized, $resolvedItems, $products);

        $token = $this->previewStorage->put($userId, [
            'input' => $normalized,
            'preview' => $preview->toArray(),
        ]);

        return ['token' => $token, 'preview' => $preview];
    }

    public function confirm(int $userId, string $token, string $idempotencyKey): Purchase
    {
        $existing = Purchase::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $existing;
        }

        return Cache::lock('receivings:idempotency:'.$idempotencyKey, 30)->block(
            5,
            function () use ($userId, $token, $idempotencyKey): Purchase {
                $existing = Purchase::query()->where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    return $existing;
                }

                $payload = $this->previewStorage->get($token, $userId);

                $purchase = DB::transaction(function () use ($payload, $userId, $idempotencyKey): Purchase {
                    $normalized = $this->validation->normalize($payload['input'] ?? []);
                    $this->validation->assertSourceReferences($normalized);
                    $productIds = array_column($normalized['items'], 'product_id');
                    $lockedProducts = $this->stockLockService->lockProducts($productIds);
                    $resolvedItems = $this->validation->resolveItems($normalized['items'], $lockedProducts);
                    $this->assertProductsExist($normalized['items'], $lockedProducts);

                    $purchase = Purchase::query()->create([
                        'supplier_id' => $normalized['supplier_id'],
                        'purchase_date' => $normalized['purchase_date'],
                        'total_amount' => $this->itemsTotal($resolvedItems),
                        'remark' => $normalized['remark'],
                        'source' => $normalized['source'],
                        'supplier_document_number' => $normalized['supplier_document_number'],
                        'status' => Purchase::STATUS_POSTED,
                        'created_by' => $userId,
                        'idempotency_key' => $idempotencyKey,
                    ]);

                    foreach ($resolvedItems as $item) {
                        $product = $lockedProducts->get((int) $item['product_id']);
                        $stockBefore = $this->decimal((string) $product->stock_qty, 4);
                        $oldAverageCost = (string) $product->cost_price;
                        $averageCost = $this->averageCostService->calculate(
                            (float) (string) $stockBefore,
                            (float) $product->cost_price,
                            (float) $item['base_qty'],
                            (float) $item['base_cost_price'],
                        );
                        $stockAfter = $stockBefore
                            ->plus($item['base_qty'])
                            ->toScale(4, RoundingMode::UNNECESSARY);

                        $product->stock_qty = (string) $stockAfter;
                        $product->cost_price = $averageCost;
                        if ($oldAverageCost !== (string) $averageCost
                            && $product->pricing_reviewed_cost === null
                            && $product->selling_price !== null) {
                            $product->pricing_reviewed_cost = $oldAverageCost;
                        }
                        $product->save();

                        $purchaseItem = $purchase->items()->create([
                            'product_id' => $product->id,
                            'product_unit_id' => $item['unit']?->id,
                            'qty' => $item['qty'],
                            'cost_price' => $item['cost_price'],
                            'total' => $this->lineTotal($item['qty'], $item['cost_price']),
                            'conversion_rate_used' => $item['conversion_rate'],
                            'base_qty' => $item['base_qty'],
                            'unit_name_snapshot' => $item['unit']?->unit?->name ?: $product->unit,
                            'unit_code_snapshot' => $item['unit']?->unit?->code,
                            'average_cost_before' => $oldAverageCost,
                            'average_cost_after' => $averageCost,
                            'stock_before' => (string) $stockBefore,
                            'stock_after' => (string) $stockAfter,
                        ]);

                        $movement = StockMovement::query()->create([
                            'product_id' => $product->id,
                            'type' => 'IN',
                            'qty' => $item['base_qty'],
                            'stock_before' => (string) $stockBefore,
                            'stock_after' => (string) $stockAfter,
                            'reference_type' => 'purchase',
                            'reference_id' => $purchase->id,
                            'remark' => 'รับสินค้าเข้า V2',
                        ]);
                        $purchaseItem->update(['stock_movement_id' => $movement->id]);
                    }

                    return $purchase->load(['supplier', 'creator', 'items.product', 'items.productUnit.unit', 'items.stockMovement']);
                });

                $this->previewStorage->forget($token);

                return $purchase;
            }
        );
    }

    private function buildPreview(array $normalized, array $items, $products): ReceiveStockPreviewData
    {
        $lines = [];
        $total = BigDecimal::zero()->toScale(2);

        foreach ($items as $item) {
            $product = $products->get((int) $item['product_id']);
            $stockBefore = $this->decimal((string) $product->stock_qty, 4);
            $stockAfter = $stockBefore->plus($item['base_qty'])->toScale(4, RoundingMode::UNNECESSARY);
            $averageCostAfter = $this->averageCostService->calculate(
                (float) (string) $stockBefore,
                (float) $product->cost_price,
                (float) $item['base_qty'],
                (float) $item['base_cost_price'],
            );
            $lineTotal = $this->lineTotal($item['qty'], $item['cost_price']);
            $total = $total->plus($lineTotal);
            $line = ReceiveStockLineData::fromModels(
                $product,
                $item['unit'],
                $item['qty'],
                $item['cost_price'],
                $item['base_qty'],
                $item['base_cost_price'],
                $lineTotal,
                (string) $stockBefore,
                (string) $stockAfter,
                (string) $averageCostAfter,
            );
            $lines[] = $line->toArray();
        }

        return new ReceiveStockPreviewData(
            source: $normalized['source'],
            supplierId: $normalized['supplier_id'],
            purchaseDate: $normalized['purchase_date'],
            supplierDocumentNumber: $normalized['supplier_document_number'],
            remark: $normalized['remark'],
            lines: $lines,
            totalAmount: (string) $total,
        );
    }

    private function assertProductsExist(array $items, $products): void
    {
        foreach ($items as $item) {
            if (! $products->has((int) $item['product_id'])) {
                throw new DomainException('ไม่พบสินค้า');
            }
        }
    }

    private function itemsTotal(array $items): string
    {
        $total = BigDecimal::zero()->toScale(2);
        foreach ($items as $item) {
            $total = $total->plus($this->lineTotal($item['qty'], $item['cost_price']));
        }

        return (string) $total;
    }

    private function lineTotal(string $qty, string $costPrice): string
    {
        return (string) BigDecimal::of($qty)
            ->multipliedBy($costPrice)
            ->toScale(2, RoundingMode::HALF_UP);
    }

    private function decimal(string $value, int $scale): BigDecimal
    {
        return BigDecimal::of($value)->toScale($scale, RoundingMode::UNNECESSARY);
    }
}
