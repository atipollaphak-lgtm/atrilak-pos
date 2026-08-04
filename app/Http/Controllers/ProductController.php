<?php

namespace App\Http\Controllers;

use App\Exceptions\StaleProductCostException;
use App\Http\Requests\Products\UpdateProductCostRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductUnit;
use App\Models\Unit;
use App\Services\Pricing\PricingService;
use App\Services\ProductBarcodeService;
use App\Services\ProductCreationService;
use App\Services\Products\ProductCostAdjustmentService;
use App\Services\ProductUnitService;
use App\Services\ProductUpdateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    protected ProductUnitService $productUnitService;

    protected ProductBarcodeService $productBarcodeService;

    protected ProductUpdateService $productUpdateService;

    protected ProductCreationService $productCreationService;

    protected ProductCostAdjustmentService $productCostAdjustmentService;

    public function __construct(
        ProductUnitService $productUnitService,
        ProductBarcodeService $productBarcodeService,
        ProductUpdateService $productUpdateService,
        ProductCreationService $productCreationService,
        ProductCostAdjustmentService $productCostAdjustmentService
    ) {
        $this->productUnitService = $productUnitService;
        $this->productBarcodeService = $productBarcodeService;
        $this->productUpdateService = $productUpdateService;
        $this->productCreationService = $productCreationService;
        $this->productCostAdjustmentService = $productCostAdjustmentService;
    }

    public function index(Request $request)
    {
        $sort = $request->input('sort', 'category_name');
        $perPage = $request->input('per_page', 50);
        $query = Product::query()
            ->with(['category', 'unitRelation'])
            ->withCount(['stockMovements', 'purchaseItems', 'saleItems']);

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($searchQuery) use ($search): void {
                foreach (['name', 'product_code', 'sku', 'barcode'] as $column) {
                    $searchQuery->orWhereRaw('LOWER(products.'.$column.') LIKE ?', ['%'.strtolower($search).'%']);
                }
            });
        }

        if ($request->filled('category_id')) {
            $query->where('products.category_id', $request->integer('category_id'));
        }

        if ($request->input('status') === 'active') {
            $query->where('products.active', true);
        } elseif ($request->input('status') === 'inactive') {
            $query->where('products.active', false);
        }

        $sorts = [
            'category_name' => ['categories.name', 'asc'],
            'name' => ['products.name', 'asc'],
            'cost_price' => ['products.cost_price', 'asc'],
            'selling_price' => ['products.selling_price', 'asc'],
            'profit' => ['products.selling_price', 'desc'],
            'stock_qty' => ['products.stock_qty', 'asc'],
            'created_at' => ['products.created_at', 'desc'],
            'updated_at' => ['products.updated_at', 'desc'],
        ];
        [$sortColumn, $sortDirection] = $sorts[$sort] ?? $sorts['category_name'];

        $query->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->select('products.*')
            ->orderBy($sortColumn, $sortDirection);

        if ($sort === 'category_name') {
            $query->orderBy('products.name');
        }

        $products = $perPage === 'all'
            ? $query->get()
            : $query->paginate(in_array((int) $perPage, [10, 20, 50, 100], true) ? (int) $perPage : 50)
                ->withQueryString();

        $categories = Category::orderBy('name')->get();

        $units = Unit::where('active', true)
            ->orderBy('name')
            ->get();

        return view('products.index', compact('products', 'categories', 'units', 'sort', 'perPage'));
    }

    public function store(Request $request)
    {
        if ($request->input('minimum_stock') === null) {
            $request->merge(['minimum_stock' => 0]);
        }

        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'unit_id' => 'nullable|exists:units,id',
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:255',
            'cost_price' => 'nullable|numeric|min:0',
            'selling_price' => 'nullable|numeric|min:0',
            'stock_qty' => 'nullable|numeric|min:0',
            'vat_enabled' => 'nullable|boolean',
            'active' => 'nullable|boolean',
            'remark' => 'nullable|string',
            'minimum_stock' => 'numeric|min:0',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('products', 'public');
        }

        $product = $this->productCreationService->create($validated);

        return redirect()->route('products.index', $this->indexQuery($request))->with(
            'success',
            'เพิ่มสินค้าเรียบร้อย'
        );
    }

    public function create()
    {
        $categories = Category::where('active', true)->get();

        $units = Unit::where('active', true)->get();

        return view(
            'products.create',
            compact('categories', 'units')
        );
    }

    public function edit(Product $product)
    {
        $categories = Category::where('active', true)->get();

        $units = Unit::where('active', true)->get();

        $product->load([
            'productUnits.unit',
            'productUnits.barcodes',
            'productUnits.priceTiers',
        ]);
        $priceHistories = $product->priceHistories()
            ->latest()
            ->take(20)
            ->get();

        $productUnits = $this->productUnitService
            ->getUnitsForProduct($product);

        $productBarcodes = $this->productBarcodeService
            ->getBarcodesForProduct($product);

        $pricingPreview = app(PricingService::class)
            ->calculate($product);

        return view(
            'products.edit',
            compact(
                'product',
                'categories',
                'units',
                'priceHistories',
                'productUnits',
                'productBarcodes',
                'pricingPreview'
            )
        );
    }

    public function update(
        Request $request,
        Product $product
    ) {
        if ($request->input('minimum_stock') === null) {
            $request->merge(['minimum_stock' => $product->minimum_stock]);
        }
        if ($request->input('vat_enabled') === null) {
            $request->merge(['vat_enabled' => $product->vat_enabled]);
        }

        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'unit_id' => 'nullable|exists:units,id',
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:255',
            'remark' => 'nullable|string',
            'vat_enabled' => 'nullable|boolean',
            'active' => 'nullable|boolean',
            'minimum_stock' => 'numeric|min:0',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $oldImagePath = $product->image_path;
        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('products', 'public');
        }

        $validated['cost_price'] = $product->cost_price;
        $validated['selling_price'] = $product->selling_price;
        $validated['stock_qty'] = $product->stock_qty;
        $product = $this->productUpdateService->update($product, $validated);

        if ($oldImagePath && isset($validated['image_path']) && $oldImagePath !== $validated['image_path']) {
            Storage::disk('public')->delete($oldImagePath);
        }

        return redirect()
            ->route('products.index', $this->indexQuery($request))
            ->with(
                'success',
                'แก้ไขสินค้าเรียบร้อย'
            );
    }

    public function updateCost(UpdateProductCostRequest $request, Product $product)
    {
        try {
            $result = $this->productCostAdjustmentService->adjust(
                $product,
                (string) $request->input('cost_price'),
                (string) $request->input('current_cost_price'),
                (string) $request->input('reason'),
                (int) $request->user()->id
            );

            return redirect()
                ->route('products.index', $this->indexQuery($request))
                ->with(
                    'success',
                    $result['changed']
                        ? 'ปรับต้นทุนสินค้าเรียบร้อยแล้ว'
                        : 'ต้นทุนสินค้าเท่าเดิม ไม่มีการเปลี่ยนแปลง'
                );
        } catch (StaleProductCostException $exception) {
            return redirect()
                ->route('products.index', $this->indexQuery($request))
                ->withInput()
                ->with('error', $exception->getMessage());
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()
                ->route('products.index', $this->indexQuery($request))
                ->withInput()
                ->with('error', 'ไม่สามารถปรับต้นทุนสินค้าได้ กรุณาลองใหม่อีกครั้ง');
        }
    }

    private function indexQuery(Request $request): array
    {
        return array_filter([
            'search' => $request->input('search'),
            'category_id' => $request->input('filter_category_id'),
            'status' => $request->input('filter_status'),
            'sort' => $request->input('filter_sort'),
            'per_page' => $request->input('filter_per_page'),
            'page' => $request->input('filter_page'),
        ], static fn ($value) => $value !== null && $value !== '');
    }

    public function destroy(Product $product)
    {
        $product->active = false;
        $product->save();

        return back()->with(
            'success',
            'ปิดใช้งานสินค้าเรียบร้อย'
        );
    }

    public function restore(Product $product)
    {
        $product->active = true;
        $product->save();

        return back()->with(
            'success',
            'เปิดใช้งานสินค้าเรียบร้อย'
        );
    }

    public function storeUnit(
        Request $request,
        Product $product
    ) {
        $request->validate([
            'unit_id' => 'required|exists:units,id',
            'conversion_rate' => 'required|numeric|min:0.0001',
            'purchase_price' => 'nullable|numeric|min:0',
            'selling_price' => 'nullable|numeric|min:0',
        ]);

        $this->productUnitService->createOrUpdateUnit(
            $product,
            [
                'unit_id' => $request->unit_id,
                'conversion_rate' => $request->conversion_rate,
                'purchase_price' => $request->purchase_price,
                'selling_price' => $request->selling_price,
                'is_purchase_unit' => $request->boolean('is_purchase_unit', true),
                'is_sale_unit' => $request->boolean('is_sale_unit', true),
                'active' => true,
            ]
        );

        return redirect()
            ->route('products.edit', $product)
            ->with('success', 'เพิ่มหน่วยสินค้าสำเร็จ');
    }

    public function updateUnit(
        Request $request,
        Product $product,
        ProductUnit $productUnit
    ) {
        $request->validate([
            'conversion_rate' => 'required|numeric|min:0.0001',
            'purchase_price' => 'nullable|numeric|min:0',
            'selling_price' => 'nullable|numeric|min:0',
        ]);

        $this->productUnitService->updateUnit(
            $productUnit,
            [
                'conversion_rate' => $request->conversion_rate,
                'purchase_price' => $request->purchase_price,
                'selling_price' => $request->selling_price,
                'is_purchase_unit' => $request->boolean('is_purchase_unit'),
                'is_sale_unit' => $request->boolean('is_sale_unit'),
                'active' => $request->boolean('active', true),
            ]
        );

        return redirect()
            ->route('products.edit', $product)
            ->with('success', 'แก้ไขหน่วยสินค้าสำเร็จ');
    }

    public function destroyUnit(
        Product $product,
        ProductUnit $productUnit
    ) {
        $this->productUnitService->deleteUnit($productUnit);

        return redirect()
            ->route('products.edit', $product)
            ->with('success', 'ลบหน่วยสินค้าสำเร็จ');
    }

    public function storeBarcode(
        Request $request,
        Product $product
    ) {
        $request->validate([
            'product_unit_id' => 'required|exists:product_units,id',
            'barcode' => 'required|string|max:255|unique:product_barcodes,barcode',
        ]);

        $productUnit = ProductUnit::findOrFail(
            $request->product_unit_id
        );

        $this->productBarcodeService->createBarcode(
            $product,
            $productUnit,
            [
                'barcode' => $request->barcode,
                'is_default' => $request->boolean('is_default'),
                'active' => true,
            ]
        );

        return redirect()
            ->route('products.edit', $product)
            ->with('success', 'เพิ่ม Barcode สำเร็จ');
    }

    public function updateBarcode(
        Request $request,
        Product $product,
        ProductBarcode $productBarcode
    ) {
        $request->validate([
            'barcode' => 'required|string|max:255|unique:product_barcodes,barcode,'.$productBarcode->id,
        ]);

        $this->productBarcodeService->updateBarcode(
            $productBarcode,
            [
                'barcode' => $request->barcode,
                'is_default' => $request->boolean('is_default'),
                'active' => $request->boolean('active', true),
            ]
        );

        return redirect()
            ->route('products.edit', $product)
            ->with('success', 'แก้ไข Barcode สำเร็จ');
    }

    public function destroyBarcode(
        Product $product,
        ProductBarcode $productBarcode
    ) {
        $this->productBarcodeService->deleteBarcode(
            $productBarcode
        );

        return redirect()
            ->route('products.edit', $product)
            ->with('success', 'ลบ Barcode สำเร็จ');
    }
}
