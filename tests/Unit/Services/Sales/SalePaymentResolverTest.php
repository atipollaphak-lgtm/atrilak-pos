<?php

namespace Tests\Unit\Services\Sales;

use App\Services\Sales\SaleDecimalService;
use App\Services\Sales\SalePaymentResolver;
use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SalePaymentResolverTest extends TestCase
{
    #[DataProvider('validPayments')]
    public function test_it_resolves_canonical_payments(
        string $total,
        string $method,
        string $cash,
        string $promptpay,
        string $received,
        array $expected
    ): void {
        $this->assertSame(
            $expected,
            $this->resolver()->resolve($total, $method, $cash, $promptpay, $received)
        );
    }

    public static function validPayments(): array
    {
        return [
            'cash exact' => [
                '850.00', 'cash', '850.00', '0.00', '850.00',
                self::payment('cash', '850.00', '0.00', '850.00', '0.00'),
            ],
            'cash with change' => [
                '850.00', 'cash', '850.00', '0.00', '1000.00',
                self::payment('cash', '850.00', '0.00', '1000.00', '150.00'),
            ],
            'promptpay' => [
                '850.00', 'promptpay', '0.00', '850.00', '0.00',
                self::payment('promptpay', '0.00', '850.00', '0.00', '0.00'),
            ],
            'mixed exact cash portion' => [
                '850.00', 'mixed', '300.00', '550.00', '300.00',
                self::payment('mixed', '300.00', '550.00', '300.00', '0.00'),
            ],
            'mixed with cash tender and change' => [
                '850.00', 'mixed', '300.00', '550.00', '500.00',
                self::payment('mixed', '300.00', '550.00', '500.00', '200.00'),
            ],
            'zero-total cash' => [
                '0.00', 'cash', '0.00', '0.00', '0.00',
                self::payment('cash', '0.00', '0.00', '0.00', '0.00'),
            ],
            'zero-total promptpay' => [
                '0.00', 'promptpay', '0.00', '0.00', '0.00',
                self::payment('promptpay', '0.00', '0.00', '0.00', '0.00'),
            ],
        ];
    }

    #[DataProvider('invalidPayments')]
    public function test_it_rejects_invalid_payment_contracts(
        mixed $total,
        mixed $method,
        mixed $cash,
        mixed $promptpay,
        mixed $received
    ): void {
        $this->expectException(DomainException::class);

        $this->resolver()->resolve($total, $method, $cash, $promptpay, $received);
    }

    public static function invalidPayments(): array
    {
        return [
            'unknown method' => ['100.00', 'card', '100.00', '0.00', '100.00'],
            'negative cash' => ['100.00', 'cash', '-100.00', '0.00', '100.00'],
            'negative promptpay' => ['100.00', 'promptpay', '0.00', '-100.00', '0.00'],
            'negative received' => ['100.00', 'cash', '100.00', '0.00', '-1.00'],
            'cash scale over two' => ['100.00', 'cash', '100.001', '0.00', '100.00'],
            'promptpay scale over two' => ['100.00', 'promptpay', '0.00', '100.001', '0.00'],
            'received scale over two' => ['100.00', 'cash', '100.00', '0.00', '100.001'],
            'total scale over two' => ['100.001', 'cash', '100.00', '0.00', '100.00'],
            'amount exceeds numeric precision' => [
                '10000000000000.00', 'cash', '10000000000000.00', '0.00', '10000000000000.00',
            ],
            'cash allocation differs from total' => ['100.00', 'cash', '90.00', '10.00', '100.00'],
            'cash includes promptpay' => ['100.00', 'cash', '100.00', '1.00', '100.00'],
            'promptpay allocation differs from total' => ['100.00', 'promptpay', '10.00', '90.00', '0.00'],
            'promptpay with received cash' => ['100.00', 'promptpay', '0.00', '100.00', '1.00'],
            'promptpay with change-producing input' => ['100.00', 'promptpay', '0.00', '100.00', '200.00'],
            'mixed with zero cash' => ['100.00', 'mixed', '0.00', '100.00', '0.00'],
            'mixed with zero promptpay' => ['100.00', 'mixed', '100.00', '0.00', '100.00'],
            'mixed allocation differs from total' => ['100.00', 'mixed', '40.00', '50.00', '40.00'],
            'received below cash' => ['100.00', 'mixed', '40.00', '60.00', '39.99'],
            'mixed zero total' => ['0.00', 'mixed', '0.00', '0.00', '0.00'],
            'missing method' => ['100.00', null, '100.00', '0.00', '100.00'],
            'missing cash' => ['100.00', 'cash', null, '0.00', '100.00'],
            'missing promptpay' => ['100.00', 'cash', '100.00', null, '100.00'],
            'missing received' => ['100.00', 'cash', '100.00', '0.00', null],
            'float input is not authoritative' => ['100.00', 'cash', 100.0, '0.00', '100.00'],
        ];
    }

    private function resolver(): SalePaymentResolver
    {
        return new SalePaymentResolver(new SaleDecimalService);
    }

    private static function payment(
        string $method,
        string $cash,
        string $promptpay,
        string $received,
        string $change
    ): array {
        return [
            'payment_method' => $method,
            'cash_amount' => $cash,
            'promptpay_amount' => $promptpay,
            'received_amount' => $received,
            'change_amount' => $change,
        ];
    }
}
