<?php

namespace App\Http\Controllers;

use App\Http\Requests\Sales\StoreSaleV3Request;
use App\Models\Category;
use App\Models\Customer;
use App\Models\DeliveryZone;
use App\Models\Product;
use App\Models\Technician;
use App\Services\SaleService;
use DomainException;
use Throwable;

class SaleV3Controller extends Controller
{
    public function index()
    {
        $customers = Customer::query()
            ->where('active', true)
            ->withCount('deliveryAddresses')
            ->orderBy('name')
            ->get();
        $categories = Category::query()
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $products = Product::query()
            ->with(['category', 'frequentProduct', 'productUnits.unit', 'productUnits.barcodes', 'productUnits.priceTiers'])
            ->where('active', true)
            ->orderBy('category_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $products = $products->sort(function (Product $left, Product $right): int {
            $leftOrder = $left->frequentProduct?->sort_order;
            $rightOrder = $right->frequentProduct?->sort_order;

            if ($leftOrder === null && $rightOrder !== null) {
                return 1;
            }
            if ($leftOrder !== null && $rightOrder === null) {
                return -1;
            }
            if ($leftOrder !== null && $rightOrder !== null && $leftOrder !== $rightOrder) {
                return $leftOrder <=> $rightOrder;
            }

            $leftCategorySort = (int) ($left->category?->sort_order ?? 0);
            $rightCategorySort = (int) ($right->category?->sort_order ?? 0);
            if ($leftCategorySort !== $rightCategorySort) {
                return $leftCategorySort <=> $rightCategorySort;
            }

            $leftProductSort = (int) ($left->sort_order ?? 0);
            $rightProductSort = (int) ($right->sort_order ?? 0);
            if ($leftProductSort !== $rightProductSort) {
                return $leftProductSort <=> $rightProductSort;
            }

            return $left->id <=> $right->id;
        })->values();
        $technicians = Technician::query()->where('active', true)->orderBy('name')->get();
        $deliveryZones = DeliveryZone::query()
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('sales-v3.index', compact('customers', 'categories', 'products', 'technicians', 'deliveryZones'));
    }

    public function store(StoreSaleV3Request $request, SaleService $saleService)
    {
        try {
            $validated = $request->validated();
            $saleData = [
                'hold_bill_id' => $validated['hold_bill_id'] ?? null,
                'customer_id' => $validated['customer_id'] ?? null,
                'customer_delivery_address_id' => $validated['customer_delivery_address_id'] ?? null,
                'pricing_zone_id' => $validated['pricing_zone_id'] ?? null,
                'technician_id' => $validated['technician_id'] ?? null,
                'sale_date' => now()->toDateString(),
                'delivery_date' => $validated['delivery_date'] ?? null,
                'delivery_type' => $validated['delivery_type'],
                'discount' => $validated['discount'] ?? 0,
                'notes' => $validated['notes'] ?? null,
                'payment_method' => $validated['payment_method'],
                'cash_amount' => $validated['cash_amount'],
                'promptpay_amount' => $validated['promptpay_amount'],
                'received_amount' => $validated['received_amount'],
                'idempotency_key' => $validated['idempotency_key'],
                'items' => $validated['items'],
            ];

            // Leave these keys absent when the caller is resuming a hold and
            // did not send an override. SaleService then restores the exact
            // fee/flag snapshot stored on the hold bill.
            if (array_key_exists('delivery_fee', $validated)) {
                $saleData['delivery_fee'] = $validated['delivery_fee'];
            }
            if (array_key_exists('delivery_fee_override_flag', $validated)) {
                $saleData['delivery_fee_override_flag'] = $validated['delivery_fee_override_flag'];
            }

            $sale = $saleService->createSale($saleData);

            return response()->json([
                'success' => true,
                'sale_id' => $sale->id,
                'sale_no' => $sale->sale_no,
                'invoice_url' => route('sales.invoice-v2', $sale->id),
                'idempotent_replay' => $sale->idempotentReplay,
                'payment' => [
                    'payment_method' => $sale->payment_method,
                    'cash_amount' => $sale->cash_amount,
                    'promptpay_amount' => $sale->promptpay_amount,
                    'received_amount' => $sale->received_amount,
                    'change_amount' => $sale->change_amount,
                ],
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
