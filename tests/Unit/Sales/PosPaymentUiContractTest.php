<?php

namespace Tests\Unit\Sales;

use PHPUnit\Framework\TestCase;

class PosPaymentUiContractTest extends TestCase
{
    public function test_v1_uses_shared_payment_modal_and_submits_only_backend_input_fields(): void
    {
        $view = $this->source('resources/views/sales/index.blade.php');

        $this->assertStringContainsString("@include('sales.partials.payment-modal')", $view);
        $this->assertStringContainsString("asset('js/modules/pos-payment.js')", $view);
        $this->assertStringContainsString('name="payment_method"', $view);
        $this->assertStringContainsString('name="cash_amount"', $view);
        $this->assertStringContainsString('name="promptpay_amount"', $view);
        $this->assertStringContainsString('name="received_amount"', $view);
        $this->assertStringNotContainsString('name="change_amount"', $view);
        $this->assertLessThan(
            strpos($view, "asset('js/modules/pos-v1-submit.js')"),
            strpos($view, "asset('js/modules/pos-payment.js')")
        );
    }

    public function test_v2_uses_the_same_payment_modal_before_its_submit_module(): void
    {
        $view = $this->source('resources/views/sales/index_v2.blade.php');

        $this->assertStringContainsString("@include('sales.partials.payment-modal')", $view);
        $this->assertStringContainsString("asset('js/modules/pos-payment.js')", $view);
        $this->assertLessThan(
            strpos($view, "asset('js/modules/pos-submit.js')"),
            strpos($view, "asset('js/modules/pos-payment.js')")
        );
    }

    public function test_shared_modal_has_the_approved_controls_and_non_submit_cancel(): void
    {
        $view = $this->source('resources/views/sales/partials/payment-modal.blade.php');

        foreach ([
            'วิธีชำระเงิน',
            'เงินสด',
            'พร้อมเพย์',
            'เงินสด + พร้อมเพย์',
            'ยอดสุทธิ',
            'เงินสดที่ใช้ชำระ',
            'ยอดพร้อมเพย์',
            'รับเงินสด',
            'เงินทอน',
            'ยืนยันการขาย',
            'ยกเลิก',
        ] as $label) {
            $this->assertStringContainsString($label, $view);
        }

        foreach ([
            'paymentModal',
            'payment-total',
            'payment-method',
            'payment-mixed-cash',
            'payment-promptpay-amount',
            'payment-received',
            'payment-change',
            'payment-error',
            'btn-confirm-payment',
            'btn-cancel-payment',
        ] as $id) {
            $this->assertStringContainsString('id="'.$id.'"', $view);
        }

        $this->assertMatchesRegularExpression(
            '/id="btn-cancel-payment"[\s\S]*?type="button"/',
            $view
        );
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 3).'/'.$path);

        $this->assertNotFalse($source);

        return $source;
    }
}
