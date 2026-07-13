<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use Illuminate\Http\Request;
use App\Models\StockMovement;
use App\Models\ProductPriceHistory;
use Illuminate\Support\Facades\DB;
use App\Services\Pricing\AverageCostService;
use App\Services\Pricing\PricingService;

class PurchaseController extends Controller
{
    public function __construct(
        private AverageCostService $averageCostService,
        private PricingService $pricingService
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

        $grandTotal = 0;

        foreach ($request->product_id as $index => $productId) {

            $qty = $request->qty[$index];
            $costPrice = $request->cost_price[$index];
            if (
                empty($productId) ||
                empty($qty) ||
                $costPrice === null ||
                $costPrice === ''
            ) {
                continue;
            }
            $grandTotal += ($qty * $costPrice);
        }
        return  DB::transaction(function () use ($request, $grandTotal) {

            // โค้ดเดิมทั้งหมดตั้งแต่สร้าง Purchase
            // จนถึงก่อน return back()
            $purchase = Purchase::create([
                'supplier_id' => $request->supplier_id,
                'purchase_date' => $request->purchase_date,
                'total_amount' => $grandTotal,
            ]);

            foreach ($request->product_id as $index => $productId) {

                $qty = $request->qty[$index];
                $costPrice = $request->cost_price[$index];
                $lineTotal = $qty * $costPrice;

                $purchase->items()->create([
                    'product_id' => $productId,
                    'qty' => $qty,
                    'cost_price' => $costPrice,
                    'total' => $lineTotal,
                ]);

                $product = Product::findOrFail($productId);

                $stockBefore = $product->stock_qty;

                $averageCost = $this->averageCostService->calculate(
                    (float) $product->stock_qty,
                    (float) $product->cost_price,
                    (float) $qty,
                    (float) $costPrice
                );

                $product->stock_qty += $qty;
                $product->cost_price = $averageCost;

                $pricing = $this->pricingService->calculate(
                    $product,
                    $averageCost
                );

                if (
                    $pricing['auto_price_enabled']
                    && !$pricing['price_lock']
                    && $pricing['changed']
                ) {

                    $product->selling_price = $pricing['final_price'];

                    ProductPriceHistory::create([
                        'product_id'            => $product->id,
                        'old_price'             => $pricing['old_price'],
                        'new_price'             => $pricing['final_price'],
                        'average_cost'          => $pricing['average_cost'],
                        'profit_percent'        => $pricing['profit_percent'],
                        'price_before_round'    => $pricing['price_before_round'],
                        'satang_rounded_price'  => $pricing['satang_rounded_price'],
                        'final_price'           => $pricing['final_price'],
                        'created_from'          => 'auto_pricing',
                        'user_id'               => $request->user()?->id,
                        'remark'                => 'Auto pricing after purchase',
                    ]);
                }

                $product->save();

                StockMovement::create([
                    'product_id'     => $product->id,
                    'type'           => 'IN',
                    'qty'            => $qty,
                    'stock_before'   => $stockBefore,
                    'stock_after'    => $product->stock_qty,
                    'reference_type' => 'purchase',
                    'reference_id'   => $purchase->id,
                    'remark'         => 'ซื้อเข้า',
                ]);
            }

            return back()->with(
                'success',
                'บันทึกการซื้อเรียบร้อย'
            );
        });
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

        $purchase->load('items.product');

        foreach ($purchase->items as $item) {

            $product = $item->product;

            if (!$product) {
                continue;
            }

            $oldStock = $product->stock_qty;
            $newStock = $oldStock - $item->qty;

            if ($newStock < 0) {
                return back()->with(
                    'error',
                    'ไม่สามารถแก้ไขได้ เพราะสต๊อกสินค้า ' . $product->name . ' จะติดลบ'
                );
            }

            $product->stock_qty = $newStock;
            $product->save();

            StockMovement::create([
                'product_id' => $product->id,
                'type' => 'OUT',
                'qty' => $item->qty,
                'stock_before' => $oldStock,
                'stock_after' => $newStock,
                'reference_type' => 'purchase_edit',
                'reference_id' => $purchase->id,
                'remark' => 'คืนรายการรับเข้าเดิมจากการแก้ไข',
            ]);
        }

        $purchase->items()->delete();

        $grandTotal = 0;

        foreach ($request->product_id as $index => $productId) {

            $qty = $request->qty[$index] ?? 0;
            $costPrice = $request->cost_price[$index] ?? 0;

            if (empty($productId) || empty($qty)) {
                continue;
            }

            $lineTotal = $qty * $costPrice;

            $purchase->items()->create([
                'product_id' => $productId,
                'qty' => $qty,
                'cost_price' => $costPrice,
                'total' => $lineTotal,
            ]);

            $product = Product::find($productId);

            $oldStock = $product->stock_qty;
            $newStock = $oldStock + $qty;

            $product->stock_qty = $newStock;
            $product->cost_price = $costPrice;
            $product->save();

            StockMovement::create([
                'product_id' => $product->id,
                'type' => 'IN',
                'qty' => $qty,
                'stock_before' => $oldStock,
                'stock_after' => $newStock,
                'reference_type' => 'purchase_edit',
                'reference_id' => $purchase->id,
                'remark' => 'รับเข้าใหม่จากการแก้ไข',
            ]);

            $grandTotal += $lineTotal;
        }

        $purchase->update([
            'supplier_id' => $request->supplier_id,
            'purchase_date' => $request->purchase_date,
            'total_amount' => $grandTotal,
        ]);

        return redirect()
            ->route('purchases.show', $purchase)
            ->with('success', 'แก้ไขการรับเข้าเรียบร้อยแล้ว');
    }
    public function destroy(Purchase $purchase)
    {
        $purchase->load('items.product');

        foreach ($purchase->items as $item) {

            $product = $item->product;

            if (!$product) {
                continue;
            }

            $oldStock = $product->stock_qty;
            $newStock = $oldStock - $item->qty;

            if ($newStock < 0) {
                return back()->with(
                    'error',
                    'ไม่สามารถลบได้ เพราะสต๊อกสินค้า ' . $product->name . ' จะติดลบ'
                );
            }

            $product->stock_qty = $newStock;
            $product->save();

            StockMovement::create([
                'product_id' => $product->id,
                'type' => 'OUT',
                'qty' => $item->qty,
                'stock_before' => $oldStock,
                'stock_after' => $newStock,
                'reference_type' => 'purchase_delete',
                'reference_id' => $purchase->id,
                'remark' => 'ลบรายการรับเข้า',
            ]);
        }

        $purchase->items()->delete();

        $purchase->delete();

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
}
