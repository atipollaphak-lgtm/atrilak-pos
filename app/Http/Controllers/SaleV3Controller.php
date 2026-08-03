<?php

namespace App\Http\Controllers;

use App\Http\Requests\Sales\StoreSaleV3Request;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Technician;
use App\Services\SaleService;
use DomainException;
use Throwable;

class SaleV3Controller extends Controller
{
    public function index()
    {
        $customers = Customer::query()->where('active', true)->orderBy('name')->get();
        $categories = Category::query()->orderBy('name')->get();
        $products = Product::query()
            ->with(['category', 'productUnits.unit', 'productUnits.barcodes', 'productUnits.priceTiers'])
            ->where('active', true)
            ->orderBy('name')
            ->get();
        $technicians = Technician::query()->where('active', true)->orderBy('name')->get();

        return view('sales-v3.index', compact('customers', 'categories', 'products', 'technicians'));
    }

    public function store(StoreSaleV3Request $request, SaleService $saleService)
    {
        try {
            $validated = $request->validated();
            $sale = $saleService->createSale([
                'hold_bill_id' => $validated['hold_bill_id'] ?? null,
                'customer_id' => $validated['customer_id'] ?? null,
                'customer_delivery_address_id' => $validated['customer_delivery_address_id'] ?? null,
                'technician_id' => $validated['technician_id'] ?? null,
                'sale_date' => $validated['sale_date'] ?? now()->toDateString(),
                'delivery_type' => $validated['delivery_type'],
                'delivery_fee' => $validated['delivery_fee'] ?? 0,
                'discount' => $validated['discount'] ?? 0,
                'notes' => $validated['notes'] ?? null,
                'payment_method' => $validated['payment_method'],
                'cash_amount' => $validated['cash_amount'],
                'promptpay_amount' => $validated['promptpay_amount'],
                'received_amount' => $validated['received_amount'],
                'idempotency_key' => $validated['idempotency_key'],
                'items' => $validated['items'],
            ]);

            return response()->json([
                'success' => true,
                'sale_id' => $sale->id,
                'sale_no' => $sale->sale_no,
                'invoice_url' => route('sales.invoice-v2', $sale->id),
                'idempotent_replay' => $sale->idempotentReplay,
            ]);
        } catch (DomainException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'errors' => [],
            ], $exception->getCode() === 409 ? 409 : 422);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'ไม่สามารถบันทึกการขายได้ กรุณาลองใหม่อีกครั้ง',
                'errors' => [],
            ], 500);
        }
    }
}
