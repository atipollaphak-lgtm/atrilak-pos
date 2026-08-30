<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Unit;
use App\Services\CatalogDeletionService;
use App\Services\UnitCodeService;
use DomainException;
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

    public function store(Request $request, UnitCodeService $unitCodeService)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'short_name' => 'required|string|max:20',
            'sort_order' => 'nullable|integer',
        ]);

        $unitCodeService->create([
            'name' => $validated['name'],
            'short_name' => $validated['short_name'],
            'active' => $request->boolean('active'),
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return redirect()
            ->route('units.index')
            ->with('success', 'บันทึกหน่วยนับเรียบร้อยแล้ว');
    }

    public function update(Request $request, Unit $unit)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'short_name' => 'required|string|max:20',
            'sort_order' => 'nullable|integer',
        ]);

        $unit->update([
            'name' => $validated['name'],
            'short_name' => $validated['short_name'],
            'active' => $request->boolean('active'),
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return redirect()
            ->route('units.index')
            ->with('success', 'แก้ไขหน่วยนับเรียบร้อยแล้ว');
    }

    public function destroy(Request $request, Unit $unit, CatalogDeletionService $deletionService)
    {
        try {
            $result = $deletionService->deleteUnit($unit);
        } catch (DomainException $exception) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }

            return redirect()
                ->route('units.index')
                ->with('error', $exception->getMessage());
        }

        if ($request->expectsJson()) {
            return response()->json($result);
        }

        return redirect()
            ->route('units.index')
            ->with('success', $result['action'] === 'deleted'
                ? 'ลบหน่วยนับเรียบร้อยแล้ว'
                : 'หน่วยนี้ถูกใช้งานแล้ว จึงปิดใช้งานเพื่อรักษาข้อมูลเดิม');
    }

    public function restore(Unit $unit, CatalogDeletionService $deletionService)
    {
        $deletionService->restoreUnit($unit);

        return back()->with('success', 'เปิดใช้งานหน่วยนับเรียบร้อยแล้ว');
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

    public function merge(Request $request, CatalogDeletionService $deletionService)
    {
        $request->validate([
            'from_unit_id' => 'required|exists:units,id',
            'to_unit_id' => 'required|exists:units,id|different:from_unit_id',
        ]);

        try {
            DB::transaction(function () use ($request, $deletionService) {
                $unitIds = [(int) $request->from_unit_id, (int) $request->to_unit_id];
                sort($unitIds, SORT_NUMERIC);
                $lockedUnits = Unit::query()
                    ->whereIn('id', $unitIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');
                $fromUnit = $lockedUnits->get((int) $request->from_unit_id);
                if ($fromUnit === null || ! $lockedUnits->has((int) $request->to_unit_id)) {
                    throw new DomainException('ไม่พบหน่วยนับที่ต้องการรวม');
                }

                if (DB::table('product_units')
                    ->where('unit_id', $fromUnit->getKey())
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->exists()) {
                    throw new DomainException(
                        'ไม่สามารถรวมหน่วยนี้ได้ เนื่องจากมีหน่วยสินค้าย่อยใช้งานอยู่ กรุณาจัดการหน่วยสินค้าก่อน'
                    );
                }

                Product::query()
                    ->where('unit_id', $request->from_unit_id)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
                Product::where('unit_id', $request->from_unit_id)
                    ->update([
                        'unit_id' => $request->to_unit_id,
                    ]);

                $deletionService->deleteUnit($fromUnit);
            });
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('units.index')
            ->with('success', 'รวมหน่วยนับเรียบร้อยแล้ว');
    }
}
