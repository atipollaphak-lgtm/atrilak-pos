<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockCount;
use App\Services\StockCountService;
use Illuminate\Http\Request;

class StockCountController extends Controller
{
    public function __construct(private StockCountService $stockCountService) {}

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

        $items = [];

        foreach ($request->product_id as $index => $productId) {
            $items[] = [
                'product_id' => $productId,
                'actual_qty' => $request->actual_qty[$index] ?? 0,
            ];
        }

        $this->stockCountService->create([
            'count_date' => $request->count_date,
            'remark' => $request->remark,
            'items' => $items,
        ]);

        return redirect()
            ->route('stock-counts.index')
            ->with('success', 'บันทึกตรวจนับสต็อกสำเร็จ');
    }
}
