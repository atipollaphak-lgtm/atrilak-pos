<?php

namespace App\Http\Controllers;

use App\Http\Requests\StockCounts\StoreStockCountRequest;
use App\Models\Product;
use App\Models\StockCount;
use App\Services\StockCountService;
use DomainException;
use Illuminate\Validation\ValidationException;
use Throwable;

class StockCountController extends Controller
{
    public function __construct(private StockCountService $stockCountService) {}

    public function index()
    {
        $products = Product::query()
            ->orderBy('name')
            ->get();

        $stockCounts = StockCount::latest()
            ->take(20)
            ->get();

        return view('stock-counts.index', compact('products', 'stockCounts'));
    }

    public function store(StoreStockCountRequest $request)
    {
        try {
            $this->stockCountService->create([
                'count_date' => $request->validated('count_date'),
                'remark' => $request->validated('remark'),
                'items' => $request->normalizedItems(),
            ]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (DomainException $exception) {
            return back()
                ->withInput()
                ->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->with('error', 'ไม่สามารถบันทึกการตรวจนับสต๊อกได้ กรุณาลองใหม่อีกครั้ง');
        }

        return redirect()
            ->route('stock-counts.index')
            ->with('success', 'บันทึกตรวจนับสต็อกสำเร็จ');
    }
}
