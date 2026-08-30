<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\CatalogDeletionService;
use App\Services\CategoryPrefixAllocator;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::query()
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('categories.index', [
            'categories' => $categories,
            'roundingOverrides' => Category::ROUNDING_OVERRIDES,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);
        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $category = DB::transaction(function () use ($validated): Category {
            $maxSortOrder = Category::query()
                ->lockForUpdate()
                ->pluck('sort_order')
                ->max();
            $validated['sort_order'] = $maxSortOrder === null ? 0 : ((int) $maxSortOrder + 1);

            $category = Category::create($validated);

            return app(CategoryPrefixAllocator::class)->ensure($category);
        });

        if ($request->expectsJson()) {
            return response()->json(['category' => $category->loadCount('products')], 201);
        }

        return back()->with('success', 'เพิ่มหมวดหมู่สินค้าเรียบร้อย');
    }

    public function update(Request $request, Category $category)
    {
        $validated = $this->validated($request, $category);
        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $category = DB::transaction(function () use ($category, $validated): Category {
            $locked = Category::query()->lockForUpdate()->findOrFail($category->getKey());
            $locked->update($validated);

            return app(CategoryPrefixAllocator::class)->ensure($locked);
        });

        if ($request->expectsJson()) {
            return response()->json(['category' => $category->fresh()->loadCount('products')]);
        }

        return back()->with('success', 'แก้ไขเรียบร้อย');
    }

    public function updateOrder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'category_ids' => ['required', 'array'],
            'category_ids.*' => ['integer', 'distinct', 'exists:categories,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'ลำดับหมวดหมู่ไม่ถูกต้อง',
                'errors' => $validator->errors(),
            ], 422);
        }

        $categoryIds = array_map('intval', $validator->validated()['category_ids']);
        $orderUpdated = DB::transaction(function () use ($categoryIds): bool {
            $currentIds = Category::query()
                ->lockForUpdate()
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
            $submittedIds = $categoryIds;
            sort($submittedIds);

            if ($submittedIds !== $currentIds) {
                return false;
            }

            foreach ($categoryIds as $sortOrder => $categoryId) {
                Category::query()
                    ->whereKey($categoryId)
                    ->update(['sort_order' => $sortOrder]);
            }

            return true;
        });

        if (! $orderUpdated) {
            return response()->json([
                'message' => 'ต้องส่งลำดับหมวดหมู่ให้ครบทุกหมวดหมู่',
                'errors' => ['category_ids' => ['ต้องส่งลำดับหมวดหมู่ให้ครบทุกหมวดหมู่']],
            ], 422);
        }

        return response()->json(['message' => 'บันทึกลำดับหมวดหมู่เรียบร้อย']);
    }

    public function destroy(Request $request, Category $category, CatalogDeletionService $deletionService)
    {
        try {
            $result = $deletionService->deleteCategory($category);
        } catch (DomainException $exception) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }

            return back()->withErrors(['category' => $exception->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json($result);
        }

        return back()->with('success', $result['action'] === 'deleted'
            ? 'ลบหมวดหมู่เรียบร้อย'
            : 'หมวดหมู่ถูกใช้งานแล้ว จึงปิดใช้งานเพื่อรักษาข้อมูลเดิม');
    }

    public function restore(Category $category, CatalogDeletionService $deletionService)
    {
        $deletionService->restoreCategory($category);

        return back()->with('success', 'เปิดใช้งานหมวดหมู่เรียบร้อยแล้ว');
    }

    private function rules(?Category $category = null): array
    {
        $ignoreId = $category?->id;

        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'code_prefix' => [
                'nullable', 'string', 'max:20', 'regex:/^[A-Z]+$/',
                'unique:categories,code_prefix'.($ignoreId ? ','.$ignoreId : ''),
            ],
            'barcode_prefix' => [
                'nullable', 'digits:3',
                'unique:categories,barcode_prefix'.($ignoreId ? ','.$ignoreId : ''),
            ],
            'active' => 'nullable|boolean',
            'rounding_override' => ['nullable', 'in:'.implode(',', Category::ROUNDING_OVERRIDES)],
        ];
    }

    private function validated(Request $request, ?Category $category = null): array|JsonResponse
    {
        $validator = Validator::make($request->all(), $this->rules($category));

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'ข้อมูลไม่ถูกต้อง',
                    'errors' => $validator->errors(),
                ], 422);
            }

            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}
