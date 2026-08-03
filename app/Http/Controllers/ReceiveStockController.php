<?php

namespace App\Http\Controllers;

use App\Http\Requests\Receivings\ConfirmReceiveStockRequest;
use App\Http\Requests\Receivings\PreviewReceiveStockRequest;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\Receivings\ReceiveStockPreviewStorageService;
use App\Services\Receivings\ReceiveStockService;
use DomainException;
use Illuminate\Http\Request;
use Throwable;

class ReceiveStockController extends Controller
{
    public function __construct(
        private ReceiveStockService $receiveStockService,
        private ReceiveStockPreviewStorageService $previewStorage,
    ) {}

    public function index(Request $request)
    {
        $query = Purchase::query()->with('supplier')->latest('purchase_date')->latest('id');

        if ($request->filled('source')) {
            $source = $request->string('source')->toString();
            $query->where(function ($builder) use ($source): void {
                $builder->where('source', $source);
                if ($source === 'supplier') {
                    $builder->orWhereNull('source');
                }
            });
        }
        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->integer('supplier_id'));
        }
        if ($request->filled('from')) {
            $query->whereDate('purchase_date', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('purchase_date', '<=', $request->date('to'));
        }
        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($builder) use ($search): void {
                $builder->where('supplier_document_number', 'like', '%'.$search.'%')
                    ->orWhereHas('supplier', fn ($supplier) => $supplier->where('name', 'like', '%'.$search.'%'));
            });
        }

        $receivings = $query->paginate(15)->withQueryString();
        $suppliers = Supplier::query()->orderBy('name')->get();

        return view('receivings.index', compact('receivings', 'suppliers'));
    }

    public function create()
    {
        $suppliers = Supplier::query()->where('active', true)->orderBy('name')->get();

        return view('receivings.create', compact('suppliers'));
    }

    public function search(Request $request)
    {
        $term = trim($request->string('q')->toString());
        if ($term === '') {
            return response()->json(['data' => []]);
        }

        $products = Product::query()
            ->where('active', true)
            ->where(function ($query) use ($term): void {
                $query->where('name', 'like', '%'.$term.'%')
                    ->orWhere('product_code', 'like', '%'.$term.'%')
                    ->orWhere('barcode', 'like', '%'.$term.'%')
                    ->orWhereHas('barcodes', fn ($barcode) => $barcode->where('barcode', $term)->where('active', true));
            })
            ->with(['productUnits' => fn ($query) => $query
                ->where('active', true)
                ->where('is_purchase_unit', true)
                ->with('unit')
                ->orderByDesc('is_base_unit')
                ->orderBy('id'), 'barcodes'])
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json([
            'data' => $products->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'product_code' => $product->product_code,
                'barcode' => $product->barcode,
                'stock_qty' => (string) $product->stock_qty,
                'cost_price' => (string) $product->cost_price,
                'selling_price' => (string) $product->selling_price,
                'units' => $product->productUnits->map(fn ($unit) => [
                    'id' => $unit->id,
                    'name' => $unit->unit?->name,
                    'code' => $unit->unit?->code,
                    'conversion_rate' => (string) $unit->conversion_rate,
                    'is_base_unit' => (bool) $unit->is_base_unit,
                ])->values(),
            ])->values(),
        ]);
    }

    public function preview(PreviewReceiveStockRequest $request)
    {
        try {
            $result = $this->receiveStockService->preview($request->user()->id, $request->validated());

            return redirect()->route('receivings.preview', $result['token']);
        } catch (DomainException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }
    }

    public function previewPage(string $token, Request $request)
    {
        try {
            $stored = $this->previewStorage->get($token, $request->user()->id);
            $preview = $stored['preview'] ?? $stored;
        } catch (Throwable $exception) {
            return redirect()->route('receivings.create')->with('error', 'Preview หมดอายุ กรุณาสร้างใหม่');
        }

        return view('receivings.preview', compact('preview', 'token'));
    }

    public function confirm(ConfirmReceiveStockRequest $request)
    {
        try {
            $purchase = $this->receiveStockService->confirm(
                $request->user()->id,
                $request->validated('preview_token'),
                $request->validated('idempotency_key'),
            );
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'ไม่สามารถยืนยันการรับสินค้าได้ กรุณาลองใหม่อีกครั้ง');
        }

        return redirect()->route('receivings.show', $purchase)
            ->with('success', 'บันทึกรับสินค้าเรียบร้อยแล้ว');
    }

    public function show(Purchase $receiving)
    {
        $receiving->load(['supplier', 'creator', 'items.product', 'items.productUnit.unit', 'items.stockMovement']);

        return view('receivings.show', compact('receiving'));
    }
}
