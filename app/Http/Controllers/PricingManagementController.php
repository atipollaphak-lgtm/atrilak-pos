<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Services\Pricing\PricingService;
use Illuminate\Http\Request;

class PricingManagementController extends Controller
{
    public function index(PricingService $pricingService)
    {
        $products = Product::with('category')
            ->orderBy('name')
            ->get();

        $pricingPreviews = [];

        $summary = [
            'total' => $products->count(),
            'auto_pricing' => 0,
            'price_lock' => 0,
            'override' => 0,
            'changed' => 0,
        ];

        foreach ($products as $product) {

            $preview = $pricingService->calculate($product);

            $pricingPreviews[$product->id] = $preview;

            if ($product->auto_price_enabled) {
                $summary['auto_pricing']++;
            }

            if ($product->price_lock) {
                $summary['price_lock']++;
            }

            if (
                !is_null($product->profit_percent)
                || !is_null($product->satang_rounding_mode)
                || !is_null($product->baht_rounding_mode)
            ) {
                $summary['override']++;
            }

            if ($preview['changed']) {
                $summary['changed']++;
            }
        }

        return view('pricing-management.index', compact(
            'products',
            'pricingPreviews',
            'summary'
        ));
    }

    public function update(
        Request $request,
        Product $product,
        PricingService $pricingService
    ) {
        $validated = $request->validate([
            'auto_price_enabled' => ['nullable', 'boolean'],
            'price_lock' => ['nullable', 'boolean'],
            'profit_percent' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'satang_rounding_mode' => ['nullable', 'string'],
            'baht_rounding_mode' => ['nullable', 'string'],
        ]);

        $pricingService->updateProductPricingSettings($product, $validated);

        return redirect()
            ->route('pricing-management.index')
            ->with('success', 'บันทึกการตั้งค่าราคาสินค้าเรียบร้อยแล้ว');
    }

    public function apply(
        Product $product,
        PricingService $pricingService
    ) {
        $pricingService->applyProductPrice($product);

        return redirect()
            ->route('pricing-management.index')
            ->with('success', 'ปรับราคาขายเรียบร้อยแล้ว');
    }

    public function recalculateAll(PricingService $pricingService)
    {
        $pricingService->recalculateAllProducts();

        return redirect()
            ->route('pricing-management.index')
            ->with('success', 'คำนวณราคาสินค้าทั้งหมดใหม่เรียบร้อยแล้ว');
    }

    public function previewAll(PricingService $pricingService)
    {
        return response()->json(
            $pricingService->previewAllChanges()
        );
    }

    public function previewCategory(
        Request $request,
        PricingService $pricingService
    ) {
        $validated = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
        ]);

        return response()->json([
            'summary' => $pricingService->previewCategorySummary(
                $validated['category_id']
            ),
            'items' => $pricingService->previewCategory(
                $validated['category_id']
            ),
        ]);
    }

    public function applyAllChanged(PricingService $pricingService)
    {
        $count = $pricingService->applyAllChangedPrices();

        return redirect()
            ->route('pricing-management.index')
            ->with('success', 'ปรับราคาสินค้าที่เปลี่ยนแปลงแล้ว ' . $count . ' รายการ');
    }

    public function applyCategory(
        Request $request,
        PricingService $pricingService
    ) {
        $validated = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
        ]);

        $result = $pricingService->applyCategoryPrices(
            $validated['category_id']
        );

        return redirect()
            ->route('pricing-management.index')
            ->with(
                'success',
                'ปรับราคาทั้งหมวดแล้ว '
                    . $result['updated']
                    . ' รายการ | ข้าม Lock: '
                    . $result['skipped_locked']
                    . ' | ปิด Auto: '
                    . $result['skipped_auto_off']
                    . ' | ราคาไม่เปลี่ยน: '
                    . $result['skipped_no_change']
            );
    }

    public function history(
        Request $request,
        PricingService $pricingService
    ) {
        $filters = $request->only([
            'from',
            'to',
            'user',
            'product',
        ]);

        $histories = $pricingService->getPriceHistories($filters);

        return view(
            'pricing-management.history',
            compact('histories')
        );
    }

    public function rollback(
        ProductPriceHistory $history,
        PricingService $pricingService
    ) {
        try {

            $pricingService->rollbackPriceHistory($history);

            return redirect()
                ->route('pricing-management.history')
                ->with('success', 'ย้อนราคาสินค้าเรียบร้อยแล้ว');
        } catch (\Throwable $e) {

            return redirect()
                ->route('pricing-management.history')
                ->with('error', $e->getMessage());
        }
    }
}
