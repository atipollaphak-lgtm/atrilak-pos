<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\Technician;
use App\Services\CommercialDocumentService;
use App\Services\SaleService;
use DomainException;
use Illuminate\Http\Request;

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

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|array',
            'qty' => 'required|array',
            'selling_price' => 'required|array',
            'technician_id' => 'nullable|exists:technicians,id',
        ]);

        $items = [];
        $grandTotal = 0;

        foreach ($request->product_id as $index => $productId) {

            $qty = $request->qty[$index] ?? 0;
            $price = $request->selling_price[$index] ?? 0;

            if (
                empty($productId) ||
                empty($qty) ||
                empty($price)
            ) {
                continue;
            }

            $product = Product::find($productId);

            if (! $product) {
                return back()->with('error', 'ไม่พบสินค้า');
            }

            if ($product->stock_qty < $qty) {
                return back()->with(
                    'error',
                    'สินค้า '.$product->name.' มีสต็อกไม่พอ'
                );
            }

            $items[] = [
                'product_id' => $productId,
                'qty' => $qty,
                'selling_price' => $price,
            ];

            $grandTotal += $qty * $price;
        }

        if (count($items) <= 0) {
            return back()->with('error', 'กรุณาเลือกรายการสินค้า');
        }

        try {

            $sale = app(SaleService::class)->createSale([
                'customer_id' => $request->customer_id,
                'customer_delivery_address_id' => $request->customer_delivery_address_id,
                'technician_id' => $request->technician_id,
                'sale_date' => $request->sale_date ?? now()->toDateString(),
                'grand_total' => $grandTotal,
                'delivery_type' => $request->delivery_type ?? 'delivery',
                'discount' => $request->discount ?? 0,
                'items' => $items,
            ]);

            return response()->json([
                'success' => true,
                'sale_id' => $sale->id,
                'invoice_url' => route('sales.invoice', $sale->id),
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function checkProfit(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'Check Profit API Ready',
        ]);
    }

    public function storeV2(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.product_id' => 'required',
            'items.*.product_unit_id' => 'nullable|integer',
            'items.*.qty' => 'required',
            'items.*.selling_price' => 'required',
            'technician_id' => 'nullable|exists:technicians,id',
        ]);

        $grandTotal = 0;
        $items = [];

        foreach ($request->items as $item) {

            $product = Product::find($item['product_id']);

            if (! $product) {
                return response()->json([
                    'success' => false,
                    'message' => 'ไม่พบสินค้า',
                ], 422);
            }

            $items[] = [
                'product_id' => $item['product_id'],
                'product_unit_id' => $item['product_unit_id'] ?? null,
                'qty' => $item['qty'],
                'selling_price' => $item['selling_price'],
            ];

            $grandTotal += $item['qty'] * $item['selling_price'];
        }

        try {

            $sale = app(SaleService::class)->createSale([
                'customer_id' => $request->customer_id,
                'customer_delivery_address_id' => $request->customer_delivery_address_id,
                'technician_id' => $request->technician_id,
                'sale_date' => $request->sale_date ?? now()->toDateString(),
                'grand_total' => $grandTotal,
                'delivery_type' => $request->delivery_type ?? 'delivery',
                'discount' => $request->discount ?? 0,
                'items' => $items,
            ]);

            return response()->json([
                'success' => true,
                'sale_id' => $sale->id,
                'invoice_url' => route('sales.invoice', $sale->id),
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function show(Sale $sale)
    {
        $sale->load([
            'customer',
            'technician',
            'items.product',
        ]);

        $totalCost = $sale->items->sum(function ($item) {
            return $item->cost_price * $item->qty;
        });

        $profit = $sale->items->sum('profit');

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
            'items.product',
        ]);

        $setting = Setting::first();

        return view('sales.print', compact('sale', 'setting'));
    }

    public function edit(Sale $sale)
    {
        $sale->load('items.product', 'items.productUnit', 'customer');

        $customers = Customer::where('active', true)->get();

        $products = Product::where('active', true)->get();

        return view(
            'sales.edit',
            compact('sale', 'customers', 'products')
        );
    }

    public function update(Request $request, Sale $sale)
    {
        $request->validate([
            'sale_date' => 'required|date',
            'product_id' => 'required|array',
            'qty' => 'required|array',
            'selling_price' => 'required|array',
            'sale_item_id' => 'nullable|array',
            'product_unit_id' => 'nullable|array',
        ]);

        try {
            app(SaleService::class)->updateSale($sale, [
                'customer_id' => $request->customer_id,
                'sale_date' => $request->sale_date,
                'product_id' => $request->product_id,
                'qty' => $request->qty,
                'selling_price' => $request->selling_price,
                'sale_item_id' => $request->sale_item_id ?? [],
                'product_unit_id' => $request->product_unit_id ?? [],
                'delivery_fee' => $request->delivery_fee ?? 0,
                'discount' => $request->discount ?? 0,
            ]);
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('sales.show', $sale->id)
            ->with('success', 'แก้ไขบิลเรียบร้อยแล้ว');
    }

    public function destroy(Sale $sale)
    {
        app(SaleService::class)->deleteSale($sale);

        return redirect()
            ->route('sales.index')
            ->with('success', 'ลบบิลและคืนสต๊อกเรียบร้อยแล้ว');
    }

    public function invoice(Sale $sale)
    {
        $sale->load([
            'customer',
            'technician',
            'items.product',
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
            'items.product',
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
