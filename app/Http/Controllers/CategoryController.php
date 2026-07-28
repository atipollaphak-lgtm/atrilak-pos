<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::latest()->get();

        return view('categories.index', [
            'categories' => $categories,
            'roundingOverrides' => Category::ROUNDING_OVERRIDES,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code_prefix' => ['nullable', 'string', 'max:20', 'regex:/^[A-Z]+$/', 'unique:categories,code_prefix'],
            'barcode_prefix' => ['nullable', 'digits:3', 'unique:categories,barcode_prefix'],
            'rounding_override' => ['nullable', 'in:'.implode(',', Category::ROUNDING_OVERRIDES)],
        ]);

        Category::create($validated);

        return back()->with('success', 'เพิ่มหมวดสินค้าเรียบร้อย');
    }

    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code_prefix' => ['nullable', 'string', 'max:20', 'regex:/^[A-Z]+$/', 'unique:categories,code_prefix,'.$category->id],
            'barcode_prefix' => ['nullable', 'digits:3', 'unique:categories,barcode_prefix,'.$category->id],
            'rounding_override' => ['nullable', 'in:'.implode(',', Category::ROUNDING_OVERRIDES)],
        ]);

        $category->update($validated);

        return back()->with('success', 'แก้ไขเรียบร้อย');
    }

    public function destroy(Category $category)
    {
        $category->delete();

        return back()->with('success', 'ลบเรียบร้อย');
    }
}
