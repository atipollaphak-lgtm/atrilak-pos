<?php

namespace App\Http\Controllers;

use App\Models\DeliveryZone;
use App\Services\CatalogDeletionService;
use DomainException;
use Illuminate\Http\Request;

class DeliveryZoneController extends Controller
{
    public function index()
    {
        $deliveryZones = DeliveryZone::orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('delivery-zones.index', compact('deliveryZones'));
    }

    public function create()
    {
        return view('delivery-zones.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'price_markup_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'rounding_increment' => ['required', 'in:0.25,0.50,1.00,5.00,10.00'],
            'minimum_profit' => ['required', 'numeric', 'min:0'],
            'active' => ['nullable', 'boolean'],
            'remark' => ['nullable', 'string'],
        ], [
            'rounding_increment.in' => 'วิธีปัดเศษไม่ถูกต้อง',
        ]);

        $validated['active'] = $request->boolean('active');

        DeliveryZone::create($validated);

        return redirect()
            ->route('delivery-zones.index')
            ->with('success', 'เพิ่มโซนจัดส่งเรียบร้อยแล้ว');
    }

    public function edit(DeliveryZone $deliveryZone)
    {
        return view('delivery-zones.edit', compact('deliveryZone'));
    }

    public function update(
        Request $request,
        DeliveryZone $deliveryZone,
        CatalogDeletionService $deletionService
    ) {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'price_markup_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'rounding_increment' => ['required', 'in:0.25,0.50,1.00,5.00,10.00'],
            'minimum_profit' => ['required', 'numeric', 'min:0'],
            'active' => ['nullable', 'boolean'],
            'remark' => ['nullable', 'string'],
        ], [
            'rounding_increment.in' => 'วิธีปัดเศษไม่ถูกต้อง',
        ]);

        $validated['active'] = $request->boolean('active');

        try {
            $deletionService->updateDeliveryZone($deliveryZone, $validated);
        } catch (DomainException $exception) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $exception->getMessage(),
                    'errors' => ['active' => [$exception->getMessage()]],
                ], 422);
            }

            return back()->withInput()->withErrors(['active' => $exception->getMessage()]);
        }

        return redirect()
            ->route('delivery-zones.index')
            ->with('success', 'แก้ไขโซนจัดส่งเรียบร้อยแล้ว');
    }

    public function destroy(Request $request, DeliveryZone $deliveryZone, CatalogDeletionService $deletionService)
    {
        try {
            $result = $deletionService->deleteDeliveryZone($deliveryZone);
        } catch (DomainException $exception) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $exception->getMessage(),
                    'errors' => ['zone' => [$exception->getMessage()]],
                ], 422);
            }

            return back()->withErrors(['zone' => $exception->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json($result);
        }

        return back()->with(
            'success',
            $result['action'] === 'deleted'
                ? 'ลบโซนเรียบร้อยแล้ว'
                : 'โซนถูกใช้งานแล้ว จึงปิดใช้งานเพื่อรักษาประวัติเดิม'
        );
    }

    public function restore(DeliveryZone $deliveryZone, CatalogDeletionService $deletionService)
    {
        $deletionService->restoreDeliveryZone($deliveryZone);

        return back()->with('success', 'เปิดใช้งานโซนเรียบร้อยแล้ว');
    }
}
