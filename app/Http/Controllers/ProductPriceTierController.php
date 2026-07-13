<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductPriceTier;
use App\Models\ProductUnit;
use App\Services\ProductPriceTierService;
use Illuminate\Http\Request;

class ProductPriceTierController extends Controller
{
    public function index(
        Request $request,
        ProductPriceTierService $productPriceTierService
    ) {
        $data = $productPriceTierService->getManagementData($request);

        return view('product-price-tiers.index', $data);
    }

    public function store(
        Request $request,
        Product $product,
        ProductUnit $productUnit,
        ProductPriceTierService $productPriceTierService
    ) {
        $validated = $request->validate([
            'min_qty' => 'required|integer|min:1',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'fixed_price' => 'nullable|numeric|min:0',
            'active' => 'nullable|boolean',
        ]);

        $productPriceTierService->storeTier(
            $productUnit,
            $validated
        );

        return redirect()
            ->route('products.edit', $product)
            ->with('success', 'เพิ่ม Price Tier สำเร็จ');
    }

    public function update(
        Request $request,
        Product $product,
        ProductUnit $productUnit,
        ProductPriceTier $productPriceTier,
        ProductPriceTierService $productPriceTierService
    ) {
        $validated = $request->validate([
            'min_qty' => 'required|integer|min:1',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'fixed_price' => 'nullable|numeric|min:0',
            'active' => 'nullable|boolean',
        ]);

        $productPriceTierService->updateTier(
            $productPriceTier,
            $validated
        );

        return redirect()
            ->route('products.edit', $product)
            ->with('success', 'แก้ไข Price Tier สำเร็จ');
    }

    public function destroy(
        Product $product,
        ProductUnit $productUnit,
        ProductPriceTier $productPriceTier,
        ProductPriceTierService $productPriceTierService
    ) {
        $productPriceTierService->deleteTier($productPriceTier);

        return redirect()
            ->route('products.edit', $product)
            ->with('success', 'ลบ Price Tier สำเร็จ');
    }

    public function storeFromManagement(
        Request $request,
        ProductPriceTierService $productPriceTierService
    ) {
        $validated = $request->validate([
            'product_unit_id' => 'required|integer|exists:product_units,id',
            'min_qty' => 'required|integer|min:1',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'fixed_price' => 'nullable|numeric|min:0',
            'active' => 'nullable|boolean',
        ]);

        $productUnit = ProductUnit::findOrFail(
            $validated['product_unit_id']
        );

        $productPriceTierService->storeTier(
            $productUnit,
            $validated
        );

        return redirect()
            ->route('product-price-tiers.index')
            ->with('success', 'เพิ่ม Price Tier สำเร็จ');
    }

    public function updateFromManagement(
        Request $request,
        ProductPriceTier $productPriceTier,
        ProductPriceTierService $productPriceTierService
    ) {
        $validated = $request->validate([
            'min_qty' => 'required|integer|min:1',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'fixed_price' => 'nullable|numeric|min:0',
            'active' => 'nullable|boolean',
        ]);

        $productPriceTierService->updateTier(
            $productPriceTier,
            $validated
        );

        return redirect()
            ->route('product-price-tiers.index')
            ->with('success', 'แก้ไข Price Tier สำเร็จ');
    }

    public function destroyFromManagement(
        ProductPriceTier $productPriceTier,
        ProductPriceTierService $productPriceTierService
    ) {
        $productPriceTierService->deleteTier(
            $productPriceTier
        );

        return redirect()
            ->route('product-price-tiers.index')
            ->with('success', 'ลบ Price Tier สำเร็จ');
    }

    public function bulkCopyData(
        ProductPriceTierService $productPriceTierService
    ) {
        return response()->json(
            $productPriceTierService->getBulkCopyData()
        );
    }

    public function bulkCopy(
        Request $request,
        ProductPriceTierService $productPriceTierService
    ) {
        $validated = $request->validate([
            'source_product_unit_id' => [
                'required',
                'exists:product_units,id',
            ],

            'target_product_unit_ids' => [
                'required',
                'array',
                'min:1',
            ],

            'target_product_unit_ids.*' => [
                'exists:product_units,id',
            ],
        ]);

        $sourceProductUnit = ProductUnit::findOrFail(
            $validated['source_product_unit_id']
        );

        $productPriceTierService->bulkCopyTiers(
            $sourceProductUnit,
            $validated['target_product_unit_ids']
        );

        return response()->json([
            'success' => true,
            'message' => 'คัดลอก Price Tier สำเร็จ',
        ]);
    }
}
