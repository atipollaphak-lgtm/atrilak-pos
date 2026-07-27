<?php

namespace App\Http\Controllers;

use App\Http\Requests\Purchases\StorePurchaseRequest;
use App\Http\Requests\Purchases\UpdatePurchaseRequest;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\PurchaseService;
use DomainException;
use Throwable;

class PurchaseController extends Controller
{
    public function __construct(
        private PurchaseService $purchaseService
    ) {}

    public function index()
    {
        $suppliers = Supplier::query()
            ->where('active', true)
            ->orderBy('name')
            ->get();

        $products = Product::query()
            ->where('active', true)
            ->orderBy('name')
            ->get();

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

    public function store(StorePurchaseRequest $request)
    {
        try {
            $this->purchaseService->create([
                'supplier_id' => $request->validated('supplier_id'),
                'purchase_date' => $request->validated('purchase_date'),
                'items' => $request->normalizedItems(),
            ], $request->user()?->id);
        } catch (DomainException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->with('error', 'ไม่สามารถบันทึกรายการซื้อเข้าได้ กรุณาลองใหม่อีกครั้ง');
        }

        return back()->with(
            'success',
            'บันทึกการซื้อเรียบร้อย'
        );
    }

    public function show(Purchase $purchase)
    {
        $purchase->load([
            'supplier',
            'items.product',
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
            'items.product',
        ]);

        $suppliers = Supplier::query()
            ->where(function ($query) use ($purchase): void {
                $query->where('active', true)
                    ->orWhere('id', $purchase->supplier_id);
            })
            ->orderBy('name')
            ->get();

        $originalProductIds = $purchase->items->pluck('product_id')->all();
        $products = Product::query()
            ->where(function ($query) use ($originalProductIds): void {
                $query->where('active', true)
                    ->orWhereIn('id', $originalProductIds);
            })
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

    public function update(UpdatePurchaseRequest $request, Purchase $purchase)
    {
        try {
            $purchase = $this->purchaseService->update($purchase, [
                'supplier_id' => $request->validated('supplier_id'),
                'purchase_date' => $request->validated('purchase_date'),
                'items' => $request->normalizedItems(),
            ]);
        } catch (DomainException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->with('error', 'ไม่สามารถแก้ไขรายการซื้อเข้าได้ กรุณาลองใหม่อีกครั้ง');
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
            'items.product',
        ]);

        return view(
            'purchases.print',
            compact('purchase')
        );
    }
}
