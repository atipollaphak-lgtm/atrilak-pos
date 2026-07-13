<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UnitController extends Controller
{
    public function index()
    {
        $units = Unit::orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('units.index', compact('units'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:30|unique:units,code',
            'name' => 'required|string|max:100',
            'short_name' => 'required|string|max:20',
            'sort_order' => 'nullable|integer',
        ]);

        Unit::create([
            'code' => strtoupper($request->code),
            'name' => $request->name,
            'short_name' => $request->short_name,
            'active' => $request->boolean('active'),
            'sort_order' => $request->sort_order ?? 0,
        ]);

        return redirect()
            ->route('units.index')
            ->with('success', 'บันทึกหน่วยนับเรียบร้อยแล้ว');
    }

    public function update(Request $request, Unit $unit)
    {
        $request->validate([
            'code' => 'required|string|max:30|unique:units,code,' . $unit->id,
            'name' => 'required|string|max:100',
            'short_name' => 'required|string|max:20',
            'sort_order' => 'nullable|integer',
        ]);

        $unit->update([
            'code' => strtoupper($request->code),
            'name' => $request->name,
            'short_name' => $request->short_name,
            'active' => $request->boolean('active'),
            'sort_order' => $request->sort_order ?? 0,
        ]);

        return redirect()
            ->route('units.index')
            ->with('success', 'แก้ไขหน่วยนับเรียบร้อยแล้ว');
    }

    public function destroy(Unit $unit)
    {
        if ($unit->productUnits()->exists()) {
            return redirect()
                ->route('units.index')
                ->with('error', 'ลบไม่ได้ เพราะหน่วยนี้ถูกใช้กับสินค้าแล้ว');
        }

        $unit->delete();

        return redirect()
            ->route('units.index')
            ->with('success', 'ลบหน่วยนับเรียบร้อยแล้ว');
    }
    public function seed()
    {
        $units = [
            ['code' => 'PCS', 'name' => 'ชิ้น', 'short_name' => 'ชิ้น', 'sort_order' => 10],
            ['code' => 'BAG', 'name' => 'ถุง', 'short_name' => 'ถุง', 'sort_order' => 20],
            ['code' => 'PACK', 'name' => 'แพ็ค', 'short_name' => 'แพ็ค', 'sort_order' => 30],
            ['code' => 'DOZEN', 'name' => 'โหล', 'short_name' => 'โหล', 'sort_order' => 40],
            ['code' => 'BOX', 'name' => 'ลัง', 'short_name' => 'ลัง', 'sort_order' => 50],
            ['code' => 'PALLET', 'name' => 'พาเลท', 'short_name' => 'พาเลท', 'sort_order' => 60],
            ['code' => 'CUBE', 'name' => 'คิว', 'short_name' => 'คิว', 'sort_order' => 70],
            ['code' => 'BUCKET', 'name' => 'บุ้งกี๋', 'short_name' => 'บุ้ง', 'sort_order' => 80],
            ['code' => 'METER', 'name' => 'เมตร', 'short_name' => 'ม.', 'sort_order' => 90],
            ['code' => 'KG', 'name' => 'กิโลกรัม', 'short_name' => 'กก.', 'sort_order' => 100],
            ['code' => 'TON', 'name' => 'ตัน', 'short_name' => 'ตัน', 'sort_order' => 110],
        ];

        foreach ($units as $unit) {
            Unit::firstOrCreate(
                ['code' => $unit['code']],
                [
                    'name' => $unit['name'],
                    'short_name' => $unit['short_name'],
                    'active' => true,
                    'sort_order' => $unit['sort_order'],
                ]
            );
        }

        return redirect()
            ->route('units.index')
            ->with('success', 'สร้างข้อมูลมาตรฐานเรียบร้อยแล้ว');
    }

    public function merge(Request $request)
    {
        $request->validate([
            'from_unit_id' => 'required|exists:units,id',
            'to_unit_id' => 'required|exists:units,id|different:from_unit_id',
        ]);

        DB::transaction(function () use ($request) {

            Product::where('unit_id', $request->from_unit_id)
                ->update([
                    'unit_id' => $request->to_unit_id,
                ]);

            Unit::where('id', $request->from_unit_id)
                ->delete();
        });

        return redirect()
            ->route('units.index')
            ->with('success', 'รวมหน่วยนับเรียบร้อยแล้ว');
    }
}
