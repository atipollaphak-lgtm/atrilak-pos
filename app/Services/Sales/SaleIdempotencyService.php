<?php

namespace App\Services\Sales;

use App\Models\Sale;
use Brick\Math\BigDecimal;
use DomainException;
use Illuminate\Database\QueryException;

class SaleIdempotencyService
{
    public function payloadHash(array $data): string
    {
        $payload = [
            'customer_id' => $this->normalizeId($data['customer_id'] ?? null),
            'customer_delivery_address_id' => $this->normalizeId($data['customer_delivery_address_id'] ?? null),
            'technician_id' => $this->normalizeId($data['technician_id'] ?? null),
            'sale_date' => (string) $data['sale_date'],
            'delivery_type' => (string) ($data['delivery_type'] ?? 'delivery'),
            'discount' => $this->normalizeDecimal($data['discount'] ?? 0),
            'items' => array_map(fn (array $item): array => [
                'product_id' => $this->normalizeId($item['product_id'] ?? null),
                'product_unit_id' => $this->normalizeId($item['product_unit_id'] ?? null),
                'qty' => $this->normalizeDecimal($item['qty'] ?? 0),
                'selling_price' => $this->normalizeDecimal($item['selling_price'] ?? 0),
            ], $data['items'] ?? []),
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    public function replay(string $key, string $payloadHash): ?Sale
    {
        $sale = Sale::query()->where('idempotency_key', $key)->first();

        if ($sale === null) {
            return null;
        }

        if (! hash_equals((string) $sale->idempotency_payload_hash, $payloadHash)) {
            throw new DomainException(
                'คำขอนี้ใช้รหัสเดิมกับข้อมูลการขายที่แตกต่างกัน กรุณาตรวจสอบรายการก่อนส่งใหม่',
                409
            );
        }

        $sale->idempotentReplay = true;

        return $sale;
    }

    public function isIdempotencyKeyViolation(QueryException $exception): bool
    {
        return ($exception->errorInfo[0] ?? null) === '23505'
            && str_contains(
                (string) ($exception->errorInfo[2] ?? $exception->getMessage()),
                'sales_idempotency_key_unique'
            );
    }

    private function normalizeId(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function normalizeDecimal(mixed $value): string
    {
        return (string) BigDecimal::of((string) $value)->stripTrailingZeros();
    }
}
