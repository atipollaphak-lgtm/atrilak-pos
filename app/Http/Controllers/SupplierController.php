<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index()
    {
        $suppliers = Supplier::latest()->get();

        return view(
            'suppliers.index',
            compact('suppliers')
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
        ]);

        Supplier::create([
            'code' => $request->code,
            'name' => $request->name,
            'phone' => $request->phone,
            'address' => $request->address,
            'remark' => $request->remark,
            'active' => 1,
        ]);

        return back()->with(
            'success',
            'เพิ่มผู้จำหน่ายเรียบร้อย'
        );
    }
    public function edit(Supplier $supplier)
    {
        return view(
            'suppliers.edit',
            compact('supplier')
        );
    }
    public function update(Request $request, Supplier $supplier)
    {
        $supplier->update([
            'code' => $request->code,
            'name' => $request->name,
            'phone' => $request->phone,
            'address' => $request->address,
            'remark' => $request->remark,
            'active' => $request->active,
        ]);

        return back()->with(
            'success',
            'แก้ไขผู้จำหน่ายเรียบร้อย'
        );
    }

    public function destroy(Supplier $supplier)
    {
        $supplier->active = false;

        $supplier->save();

        return back()->with(
            'success',
            'ปิดใช้งานผู้จำหน่ายเรียบร้อย'
        );
    }
}
