<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\TechnicianCommissionRule;
use Illuminate\Http\Request;

class TechnicianCommissionRuleController extends Controller
{
    public function index()
    {
        $rules = TechnicianCommissionRule::with(['category', 'product'])
            ->latest()
            ->get();

        return view('technician_commission_rules.index', compact('rules'));
    }

    public function create()
    {
        $categories = Category::orderBy('name')->get();
        $products = Product::orderBy('name')->get();

        return view('technician_commission_rules.create', compact(
            'categories',
            'products'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'product_id' => 'nullable|exists:products,id',
            'rule_type' => 'required|in:percent,amount',
            'rule_value' => 'required|numeric|min:0',
            'active' => 'nullable|boolean',
            'remark' => 'nullable|string',
        ]);

        TechnicianCommissionRule::create([
            'name' => $request->name,
            'category_id' => $request->category_id,
            'product_id' => $request->product_id,
            'rule_type' => $request->rule_type,
            'rule_value' => $request->rule_value,
            'active' => $request->has('active'),
            'remark' => $request->remark,
        ]);

        return redirect()
            ->route('technician-commission-rules.index')
            ->with('success', 'เพิ่มกฎค่าช่างเรียบร้อยแล้ว');
    }

    public function edit(TechnicianCommissionRule $technicianCommissionRule)
    {
        $categories = Category::orderBy('name')->get();
        $products = Product::orderBy('name')->get();

        return view('technician_commission_rules.edit', compact(
            'technicianCommissionRule',
            'categories',
            'products'
        ));
    }

    public function update(Request $request, TechnicianCommissionRule $technicianCommissionRule)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'product_id' => 'nullable|exists:products,id',
            'rule_type' => 'required|in:percent,amount',
            'rule_value' => 'required|numeric|min:0',
            'active' => 'nullable|boolean',
            'remark' => 'nullable|string',
        ]);

        $technicianCommissionRule->update([
            'name' => $request->name,
            'category_id' => $request->category_id,
            'product_id' => $request->product_id,
            'rule_type' => $request->rule_type,
            'rule_value' => $request->rule_value,
            'active' => $request->has('active'),
            'remark' => $request->remark,
        ]);

        return redirect()
            ->route('technician-commission-rules.index')
            ->with('success', 'แก้ไขกฎค่าช่างเรียบร้อยแล้ว');
    }

    public function destroy(TechnicianCommissionRule $technicianCommissionRule)
    {
        $technicianCommissionRule->delete();

        return redirect()
            ->route('technician-commission-rules.index')
            ->with('success', 'ลบกฎค่าช่างเรียบร้อยแล้ว');
    }
}
