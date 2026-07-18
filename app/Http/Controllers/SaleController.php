<?php

namespace App\Http\Controllers;

use App\Exceptions\StaleSaleRevisionException;
use App\Http\Requests\Sales\StoreSaleV1Request;
use App\Http\Requests\Sales\StoreSaleV2Request;
use App\Http\Requests\Sales\UpdateSaleRequest;
use App\Http\Requests\Sales\VoidSaleRequest;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\Technician;
use App\Services\CommercialDocumentService;
use App\Services\Sales\SaleFinancialSnapshotService;
use App\Services\SaleService;
use DomainException;
use Illuminate\Http\Request;
use Throwable;

class SaleController extends Controller
{
    public function index()
    {
        $customers = Customer::where(
            'active',
            true
        )->get();

        $products = Product::with([
            'category',
            'productUnits.unit',
            'productUnits.barcodes',
            'productUnits.priceTiers',
        ])
            ->where('active', true)
            ->get();

        $sales = Sale::with('customer')
            ->latest()
            ->take(20)
            ->get();

        $technicians = Technician::where('active', true)->get();

        return view(
            'sales.index',
            compact(
                'customers',
                'products',
                'sales',
                'technicians'
            )
        );
    }

    public function indexV2()
    {
        $customers = Customer::where('active', true)->get();

        $products = Product::with([
            'category',
            'productUnits.unit',
            'productUnits.barcodes',
            'productUnits.priceTiers',
        ])
            ->where('active', true)
            ->get();

        $sales = Sale::with('customer')
            ->latest()
            ->take(20)
            ->get();

        $technicians = Technician::where('active', true)->get();

        return view(
            'sales.index_v2',
            compact(
                'customers',
                'products',
                'sales',
                'technicians'
            )
        );
    }

    public function store(StoreSaleV1Request $request)
    {
        $validated = $request->validated();

        try {
            $sale = app(SaleService::class)->createSale([
                'customer_id' => $validated['customer_id'] ?? null,
                'customer_delivery_address_id' => $validated['customer_delivery_address_id'] ?? null,
                'technician_id' => $validated['technician_id'] ?? null,
                'sale_date' => $validated['sale_date'] ?? now()->toDateString(),
                'delivery_type' => $validated['delivery_type'] ?? 'delivery',
                'discount' => $validated['discount'] ?? 0,
                'payment_method' => $validated['payment_method'],
                'cash_amount' => $validated['cash_amount'],
                'promptpay_amount' => $validated['promptpay_amount'],
                'received_amount' => $validated['received_amount'],
                'idempotency_key' => $validated['idempotency_key'],
                'items' => $request->normalizedItems(),
            ]);

            return $this->saleCreatedResponse($sale);
        } catch (DomainException $exception) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $exception->getMessage(),
                    'errors' => [],
                ], $exception->getCode() === 409 ? 409 : 422);
            }

            return back()->withInput()->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'ไม่สามารถบันทึกการขายได้ กรุณาลองใหม่อีกครั้ง',
                    'errors' => [],
                ], 500);
            }

            return back()->withInput()->with(
                'error',
                'ไม่สามารถบันทึกการขายได้ กรุณาลองใหม่อีกครั้ง'
            );
        }
    }

    public function checkProfit(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'Check Profit API Ready',
        ]);
    }

    public function storeV2(StoreSaleV2Request $request)
    {
        $validated = $request->validated();

        try {
            $sale = app(SaleService::class)->createSale([
                'customer_id' => $validated['customer_id'] ?? null,
                'customer_delivery_address_id' => $validated['customer_delivery_address_id'] ?? null,
                'technician_id' => $validated['technician_id'] ?? null,
                'sale_date' => $validated['sale_date'] ?? now()->toDateString(),
                'delivery_type' => $validated['delivery_type'] ?? 'delivery',
                'discount' => $validated['discount'] ?? 0,
                'payment_method' => $validated['payment_method'],
                'cash_amount' => $validated['cash_amount'],
                'promptpay_amount' => $validated['promptpay_amount'],
                'received_amount' => $validated['received_amount'],
                'idempotency_key' => $validated['idempotency_key'],
                'items' => $validated['items'],
            ]);

            return $this->saleCreatedResponse($sale);
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

    public function show(Sale $sale, SaleFinancialSnapshotService $financialSnapshots)
    {
        $sale->load([
            'customer',
            'technician',
            'items.product',
        ]);

        $financialSummary = $financialSnapshots->saleSummary($sale);
        $totalCost = $financialSummary['cost'];
        $profit = $financialSummary['profit'];

        return view(
            'sales.show',
            compact(
                'sale',
                'totalCost',
                'profit'
            )
        );
    }

    public function print(Sale $sale)
    {
        $sale->load([
            'customer',
            'items.product.unitRelation',
            'items.productUnit.unit',
        ]);

        $setting = Setting::first();

        return view('sales.print', compact('sale', 'setting'));
    }

    public function edit(Sale $sale)
    {
        if ($sale->isVoided()) {
            abort(409, 'ใบขายนี้ถูกยกเลิกแล้ว ไม่สามารถแก้ไขได้');
        }

        $sale->load('items.product', 'items.productUnit', 'customer');

        $customers = Customer::query()
            ->where(function ($query) use ($sale): void {
                $query->where('active', true);

                if ($sale->customer_id !== null) {
                    $query->orWhere('id', $sale->customer_id);
                }
            })
            ->orderBy('name')
            ->get();

        $historicalProductIds = $sale->items
            ->pluck('product_id')
            ->filter()
            ->unique()
            ->values();

        $products = Product::query()
            ->where(function ($query) use ($historicalProductIds): void {
                $query->where('active', true)
                    ->orWhereIn('id', $historicalProductIds);
            })
            ->orderBy('name')
            ->get();

        return view(
            'sales.edit',
            compact('sale', 'customers', 'products')
        );
    }

    public function update(UpdateSaleRequest $request, Sale $sale)
    {
        $validated = $request->validated();
        $updateData = [
            'customer_id' => $validated['customer_id'] ?? null,
            'sale_date' => $validated['sale_date'],
            'items' => $request->normalizedItems(),
            'delivery_fee' => $validated['delivery_fee'] ?? 0,
            'discount' => $validated['discount'] ?? 0,
        ];

        foreach (['payment_method', 'cash_amount', 'promptpay_amount', 'received_amount'] as $field) {
            if (array_key_exists($field, $validated)) {
                $updateData[$field] = $validated[$field];
            }
        }

        try {
            app(SaleService::class)->updateSale(
                $sale,
                $updateData,
                (int) $validated['revision']
            );
        } catch (StaleSaleRevisionException $exception) {
            return redirect()
                ->route('sales.edit', $sale->id)
                ->with('error', $exception->getMessage());
        } catch (DomainException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->with(
                'error',
                'ไม่สามารถแก้ไขการขายได้ กรุณาลองใหม่อีกครั้ง'
            );
        }

        return redirect()
            ->route('sales.show', $sale->id)
            ->with('success', 'แก้ไขบิลเรียบร้อยแล้ว');
    }

    public function destroy(Sale $sale)
    {
        try {
            app(SaleService::class)->deleteSale($sale);
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return back()->with(
                'error',
                'ไม่สามารถลบใบขายได้ กรุณาลองใหม่อีกครั้ง'
            );
        }

        return redirect()
            ->route('sales.index')
            ->with('success', 'ลบบิลและคืนสต๊อกเรียบร้อยแล้ว');
    }

    public function void(VoidSaleRequest $request, Sale $sale)
    {
        try {
            app(SaleService::class)->voidSale(
                $sale,
                $request->user(),
                $request->validated('void_reason')
            );
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return back()->with(
                'error',
                'ไม่สามารถยกเลิกใบขายได้ กรุณาลองใหม่อีกครั้ง'
            );
        }

        return redirect()
            ->route('sales.show', $sale)
            ->with('success', 'ยกเลิกใบขายและคืนสต็อกเรียบร้อยแล้ว');
    }

    private function saleCreatedResponse(Sale $sale)
    {
        return response()->json([
            'success' => true,
            'sale_id' => $sale->id,
            'sale_no' => $sale->sale_no,
            'invoice_url' => route('sales.invoice', $sale->id),
            'idempotent_replay' => $sale->idempotentReplay,
        ]);
    }

    public function invoice(Sale $sale)
    {
        $sale->load([
            'customer',
            'technician',
            'items.product.unitRelation',
            'items.productUnit.unit',
        ]);

        $setting = Setting::first();

        return view('sales.invoice', compact('sale', 'setting'));
    }

    public function invoiceV2(
        Request $request,
        Sale $sale,
        CommercialDocumentService $commercialDocumentService
    ) {
        $sale->load([
            'customer',
            'technician',
            'items.product.unitRelation',
            'items.productUnit.unit',
        ]);

        $setting = Setting::first();

        $document = $commercialDocumentService->buildSaleDocument(
            $sale,
            $request->query(
                'document_type',
                'delivery-note'
            )
        );

        return view(
            'sales.invoice_v2',
            compact(
                'sale',
                'setting',
                'document'
            )
        );
    }

    public function history()
    {
        $sales = Sale::with('customer')
            ->latest()
            ->take(50)
            ->get();

        return view(
            'sales.history',
            compact('sales')
        );
    }
}
