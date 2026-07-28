<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::query()
            ->withCount('products')
            ->latest()
            ->get();

        return view('categories.index', [
            'categories' => $categories,
            'roundingOverrides' => Category::ROUNDING_OVERRIDES,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());
        $category = Category::create($validated);

        if ($request->expectsJson()) {
            return response()->json(['category' => $category->loadCount('products')], 201);
        }

        return back()->with('success', 'เพิ่มหมวดหมู่สินค้าเรียบร้อย');
    }

    public function update(Request $request, Category $category)
    {
        $validated = $request->validate($this->rules($category));
        $category->update($validated);

        if ($request->expectsJson()) {
            return response()->json(['category' => $category->fresh()->loadCount('products')]);
        }

        return back()->with('success', 'แก้ไขเรียบร้อย');
    }

    public function destroy(Category $category)
    {
        if ($category->products()->exists()) {
            $message = 'ไม่สามารถลบหมวดหมู่ที่มีสินค้าได้';

            if (request()->expectsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return back()->withErrors(['category' => $message]);
        }

        $category->delete();

        if (request()->expectsJson()) {
            return response()->json(['message' => 'ลบหมวดหมู่เรียบร้อย']);
        }

        return back()->with('success', 'ลบเรียบร้อย');
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
}
