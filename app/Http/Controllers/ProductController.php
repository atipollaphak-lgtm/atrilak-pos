<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use App\Models\Unit;
use App\Models\ProductPriceHistory;
use App\Models\ProductPriceTier;
use App\Models\ProductUnit;
use App\Services\ProductUnitService;
use App\Services\ProductBarcodeService;
use App\Services\ProductUpdateService;

class ProductController extends Controller
{
    protected ProductUnitService $productUnitService;
    protected ProductBarcodeService $productBarcodeService;
    protected ProductUpdateService $productUpdateService;

    public function __construct(
        ProductUnitService $productUnitService,
        ProductBarcodeService $productBarcodeService,
        ProductUpdateService $productUpdateService
    ) {
        $this->productUnitService = $productUnitService;
        $this->productBarcodeService = $productBarcodeService;
        $this->productUpdateService = $productUpdateService;
    }
    public function index()
    {
        $products = Product::with([
    'category',
    'unitRelation',
    'priceTiers'
])
    ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
    ->select('products.*')
    ->orderBy('categories.name')
    ->orderBy('products.name')
    ->get();

        $categories = Category::orderBy('name')->get();

        $units = Unit::where('active', true)
            ->orderBy('name')
            ->get();

        return view('products.index', compact(
            'products',
            'categories',
            'units'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'category_id' => 'required',
            'name' => 'required',
            'unit_id' => 'nullable|exists:units,id',
        ]);

        $product = Product::create($request->all());

        if ($request->unit_id) {
            $this->productUnitService->createOrUpdateBaseUnit(
                $product,
                [
                    'unit_id' => $request->unit_id,
                    'purchase_price' => $request->cost_price,
                    'selling_price' => $request->selling_price,
                ]
            );
        }

        return back()->with(
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

        $pricingPreview = app(\App\Services\Pricing\PricingService::class)
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
        $request->validate([
            'category_id' => 'required',
            'name' => 'required',
            'unit_id' => 'nullable|exists:units,id',
        ]);
        $product = $this->productUpdateService->update($product, $request->all());

        return redirect()
            ->route('products.edit', $product)
            ->with(
                'success',
                'แก้ไขสินค้าเรียบร้อย'
            );
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
        \App\Models\ProductUnit $productUnit
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
        \App\Models\ProductBarcode $productBarcode
    ) {
        $request->validate([
            'barcode' => 'required|string|max:255|unique:product_barcodes,barcode,' . $productBarcode->id,
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
        \App\Models\ProductBarcode $productBarcode
    ) {
        $this->productBarcodeService->deleteBarcode(
            $productBarcode
        );

        return redirect()
            ->route('products.edit', $product)
            ->with('success', 'ลบ Barcode สำเร็จ');
    }
}
