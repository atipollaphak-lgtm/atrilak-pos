<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use Illuminate\Http\Request;
use App\Services\PurchaseService;
use DomainException;

class PurchaseController extends Controller
{
    public function __construct(
        private PurchaseService $purchaseService
    ) {}
    public function index()
    {
        $suppliers = Supplier::orderBy('name')->get();

        $products = Product::orderBy('name')->get();

        $purchases = Purchase::with('supplier')
            ->latest()
            ->get();

        return view(
            'purchases.index',
            compact(
                'suppliers',
                'products',
                'purchases'
            )
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'supplier_id' => 'required',

            'product_id' => 'required|array',
            'product_id.*' => 'required',

            'qty' => 'required|array',
            'qty.*' => 'required|numeric|min:1',

            'cost_price' => 'required|array',
            'cost_price.*' => 'required|numeric|min:0',
        ]);

        $this->purchaseService->create([
            'supplier_id' => $request->supplier_id,
            'purchase_date' => $request->purchase_date,
            'items' => $this->itemsFromRequest($request),
        ], $request->user()?->id);

        return back()->with(
            'success',
            'บันทึกการซื้อเรียบร้อย'
        );
    }
    public function show(Purchase $purchase)
    {
        $purchase->load([
            'supplier',
            'items.product'
        ]);

        return view(
            'purchases.show',
            compact('purchase')
        );
    }
    public function edit(Purchase $purchase)
    {
        $purchase->load([
            'supplier',
            'items.product'
        ]);

        $suppliers = Supplier::where('active', true)
            ->orderBy('name')
            ->get();

        $products = Product::where('active', true)
            ->orderBy('name')
            ->get();

        return view(
            'purchases.edit',
            compact(
                'purchase',
                'suppliers',
                'products'
            )
        );
    }
    public function update(Request $request, Purchase $purchase)
    {
        $request->validate([
            'supplier_id' => 'required',
            'purchase_date' => 'required|date',
            'product_id' => 'required|array',
            'qty' => 'required|array',
            'cost_price' => 'required|array',
        ]);

        try {
            $purchase = $this->purchaseService->update($purchase, [
                'supplier_id' => $request->supplier_id,
                'purchase_date' => $request->purchase_date,
                'items' => $this->itemsFromRequest($request),
            ]);
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('purchases.show', $purchase)
            ->with('success', 'แก้ไขการรับเข้าเรียบร้อยแล้ว');
    }
    public function destroy(Purchase $purchase)
    {
        try {
            $this->purchaseService->delete($purchase);
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('purchases.index')
            ->with('success', 'ลบรายการรับเข้าเรียบร้อยแล้ว');
    }
    public function print(Purchase $purchase)
    {
        $purchase->load([
            'supplier',
            'items.product'
        ]);

        return view(
            'purchases.print',
            compact('purchase')
        );
    }

    private function itemsFromRequest(Request $request): array
    {
        $items = [];

        foreach ($request->product_id as $index => $productId) {
            $qty = $request->qty[$index] ?? 0;
            $costPrice = $request->cost_price[$index] ?? 0;

            if (empty($productId) || empty($qty) || $costPrice === null || $costPrice === '') {
                continue;
            }

            $items[] = [
                'product_id' => $productId,
                'qty' => $qty,
                'cost_price' => $costPrice,
            ];
        }

        return $items;
    }
}
