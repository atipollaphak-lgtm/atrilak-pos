<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryPricingRuleRequest;
use App\Models\Category;
use App\Models\CategoryPricingRule;
use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Services\Pricing\CategoryPricingService;
use App\Services\Pricing\PricingService;
use Illuminate\Http\Request;

class PricingManagementController extends Controller
{
    public function index(PricingService $pricingService, CategoryPricingService $categoryPricingService)
    {
        $products = Product::query()->with('category')->get()
            ->map(fn (Product $product): array => $pricingService->calculate($product))
            ->sortBy(fn (array $product): array => [
                ['pending_review' => 0, 'unpriced' => 1, 'normal' => 2, 'inactive' => 3][$product['status']] ?? 4,
                strtolower((string) $product['product_name']),
            ])->values();

        $summary = [
            'pending_review' => $products->where('status', 'pending_review')->count(),
            'unpriced' => $products->where('status', 'unpriced')->count(),
            'normal' => $products->where('status', 'normal')->count(),
        ];

        $categoryRules = $categoryPricingService->list();
        $categories = Category::query()->where('active', true)->orderBy('name')->get(['id', 'name']);

        return view('pricing-management.index', compact('products', 'summary', 'categoryRules', 'categories'));
    }

    public function show(Product $product, PricingService $pricingService)
    {
        return response()->json($pricingService->calculate($product->load('category')));
    }

    public function update(Request $request, Product $product, PricingService $pricingService)
    {
        $validated = $request->validate([
            'pricing_method' => ['required', 'in:category,percentage,fixed,manual'],
            'pricing_value' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'rounding_direction' => ['nullable', 'in:up,down,nearest'],
            'rounding_unit' => ['nullable', 'in:0.01,0.05,0.10,0.50,1,5,10,100'],
        ]);

        $result = $pricingService->review($product, $validated);

        return response()->json($result);
    }

    public function categoryRules(CategoryPricingService $categoryPricingService)
    {
        return response()->json($categoryPricingService->list());
    }

    public function storeCategoryRule(
        CategoryPricingRuleRequest $request,
        CategoryPricingService $categoryPricingService
    ) {
        try {
            $rule = $categoryPricingService->save($request->validated());
        } catch (\DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json($rule->load('category'), 201);
    }

    public function updateCategoryRule(
        CategoryPricingRuleRequest $request,
        CategoryPricingRule $categoryPricingRule,
        CategoryPricingService $categoryPricingService
    ) {
        try {
            $rule = $categoryPricingService->save($request->validated(), $categoryPricingRule);
        } catch (\DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json($rule->load('category'));
    }

    public function destroyCategoryRule(
        CategoryPricingRule $categoryPricingRule,
        CategoryPricingService $categoryPricingService
    ) {
        try {
            $categoryPricingService->disable($categoryPricingRule);
        } catch (\DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['message' => 'ปิดกฎราคาตามหมวดแล้ว']);
    }

    public function history(Request $request, PricingService $pricingService)
    {
        $histories = ProductPriceHistory::query()
            ->with(['product', 'user'])
            ->when($request->filled('from'), fn ($query) => $query->whereDate('created_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('created_at', '<=', $request->input('to')))
            ->when($request->filled('product'), fn ($query) => $query->whereHas('product', fn ($product) => $product->where('name', 'like', '%'.$request->input('product').'%')))
            ->latest()
            ->paginate(20);

        return view('pricing-management.history', compact('histories'));
    }

    public function rollback(ProductPriceHistory $history, PricingService $pricingService)
    {
        $pricingService->rollbackPriceHistory($history);

        return redirect()->route('pricing-management.history')->with('success', 'ย้อนราคาสินค้าเรียบร้อยแล้ว');
    }
}
