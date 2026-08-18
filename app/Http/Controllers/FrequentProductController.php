<?php

namespace App\Http\Controllers;

use App\Models\FrequentProduct;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class FrequentProductController extends Controller
{
    public function index()
    {
        $frequentProducts = FrequentProduct::query()
            ->with(['product.category'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $products = Product::query()
            ->with(['category', 'frequentProduct'])
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return view('frequent-products.index', compact('frequentProducts', 'products'));
    }

    public function pin(Product $product): JsonResponse|RedirectResponse
    {
        if (! $product->active) {
            return $this->pinError($product, 'ไม่สามารถปักหมุดสินค้าที่ปิดใช้งานได้');
        }

        [$frequentProduct, $created] = DB::transaction(function () use ($product): array {
            $existing = FrequentProduct::query()
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return [$existing, false];
            }

            $maxSortOrder = FrequentProduct::query()
                ->lockForUpdate()
                ->pluck('sort_order')
                ->max();
            $frequentProduct = FrequentProduct::query()->create([
                'product_id' => $product->id,
                'sort_order' => $maxSortOrder === null ? 0 : ((int) $maxSortOrder + 1),
            ]);

            return [$frequentProduct, true];
        });

        if ($this->expectsJson()) {
            return response()->json([
                'frequent_product' => $frequentProduct->load('product.category'),
            ], $created ? 201 : 200);
        }

        return back()->with('success', $created ? 'เพิ่มสินค้าขายบ่อยแล้ว' : 'สินค้านี้อยู่ในสินค้าขายบ่อยแล้ว');
    }

    public function unpin(FrequentProduct $frequentProduct): JsonResponse|RedirectResponse
    {
        $frequentProduct->delete();

        if ($this->expectsJson()) {
            return response()->json(['message' => 'นำสินค้าออกจากสินค้าขายบ่อยแล้ว']);
        }

        return back()->with('success', 'นำสินค้าออกจากสินค้าขายบ่อยแล้ว');
    }

    public function updateOrder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'frequent_product_ids' => ['required', 'array'],
            'frequent_product_ids.*' => ['integer', 'distinct', 'exists:pos_v3_frequent_products,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'ลำดับสินค้าขายบ่อยไม่ถูกต้อง',
                'errors' => $validator->errors(),
            ], 422);
        }

        $frequentProductIds = array_map('intval', $validator->validated()['frequent_product_ids']);
        $orderUpdated = DB::transaction(function () use ($frequentProductIds): bool {
            $currentIds = FrequentProduct::query()
                ->lockForUpdate()
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
            $submittedIds = $frequentProductIds;
            sort($submittedIds);

            if ($submittedIds !== $currentIds) {
                return false;
            }

            foreach ($frequentProductIds as $sortOrder => $frequentProductId) {
                FrequentProduct::query()
                    ->whereKey($frequentProductId)
                    ->update(['sort_order' => $sortOrder]);
            }

            return true;
        });

        if (! $orderUpdated) {
            return response()->json([
                'message' => 'ต้องส่งลำดับสินค้าขายบ่อยให้ครบทุกสินค้า',
                'errors' => ['frequent_product_ids' => ['ต้องส่งลำดับสินค้าขายบ่อยให้ครบทุกสินค้า']],
            ], 422);
        }

        return response()->json(['message' => 'บันทึกลำดับสินค้าขายบ่อยเรียบร้อย']);
    }

    private function pinError(Product $product, string $message): JsonResponse|RedirectResponse
    {
        if ($this->expectsJson()) {
            return response()->json(['message' => $message], 422);
        }

        return back()->with('error', $message);
    }

    private function expectsJson(): bool
    {
        return request()->expectsJson();
    }
}
