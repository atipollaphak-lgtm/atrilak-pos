<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockCount;
use App\Models\StockCountItem;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockCountController extends Controller
{
    public function index()
    {
        $products = Product::where('active', true)
            ->orderBy('name')
            ->get();

        $stockCounts = StockCount::latest()
            ->take(20)
            ->get();

        return view(
            'stock-counts.index',
            compact(
                'products',
                'stockCounts'
            )
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'count_date' => 'required|date',
            'product_id' => 'required|array',
            'actual_qty' => 'required|array',
        ]);

        DB::transaction(function () use ($request) {

            $countDate = $request->count_date;

            $running = StockCount::whereDate(
                'count_date',
                $countDate
            )->count() + 1;

            $countNo = 'SC-' .
                date('Ymd', strtotime($countDate)) .
                '-' .
                str_pad($running, 4, '0', STR_PAD_LEFT);

            $stockCount = StockCount::create([
                'count_no' => $countNo,
                'count_date' => $countDate,
                'remark' => $request->remark,
            ]);

            foreach ($request->product_id as $index => $productId) {

                if (!$productId) {
                    continue;
                }

                $product = Product::find($productId);

                if (!$product) {
                    continue;
                }

                $systemQty = (int) $product->stock_qty;
                $actualQty = (int) ($request->actual_qty[$index] ?? 0);
                $difference = $actualQty - $systemQty;

                StockCountItem::create([
                    'stock_count_id' => $stockCount->id,
                    'product_id' => $product->id,
                    'system_qty' => $systemQty,
                    'actual_qty' => $actualQty,
                    'difference' => $difference,
                ]);

                if ($difference != 0) {

                    StockMovement::create([
                        'product_id' => $product->id,
                        'type' => 'ADJUST',
                        'qty' => $difference,
                        'stock_before' => $systemQty,
                        'stock_after' => $actualQty,
                        'reference_type' => StockCount::class,
                        'reference_id' => $stockCount->id,
                        'remark' => 'ตรวจนับสต็อก ' . $countNo,
                    ]);

                    $product->update([
                        'stock_qty' => $actualQty,
                    ]);
                }
            }
        });

        return redirect()
            ->route('stock-counts.index')
            ->with('success', 'บันทึกตรวจนับสต็อกสำเร็จ');
    }
}
