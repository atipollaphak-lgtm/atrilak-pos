<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::latest()->get();

        return view('categories.index', compact('categories'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required'
        ]);

        Category::create([
            'name' => $request->name
        ]);

        return back()->with('success', 'เพิ่มหมวดสินค้าเรียบร้อย');
    }

    public function update(Request $request, Category $category)
    {
        $request->validate([
            'name' => 'required'
        ]);

        $category->update([
            'name' => $request->name
        ]);

        return back()->with('success', 'แก้ไขเรียบร้อย');
    }

    public function destroy(Category $category)
    {
        $category->delete();

        return back()->with('success', 'ลบเรียบร้อย');
    }
}
