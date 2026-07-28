<?php

namespace App\Http\Controllers;

use App\Models\DeliveryZone;
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
            'minimum_profit' => ['required', 'numeric', 'min:0'],
            'active' => ['nullable', 'boolean'],
            'remark' => ['nullable', 'string'],
        ]);

        $validated['active'] = $request->has('active');

        DeliveryZone::create($validated);

        return redirect()
            ->route('delivery-zones.index')
            ->with('success', 'เพิ่มโซนจัดส่งเรียบร้อยแล้ว');
    }

    public function edit(DeliveryZone $deliveryZone)
    {
        return view('delivery-zones.edit', compact('deliveryZone'));
    }

    public function update(Request $request, DeliveryZone $deliveryZone)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'price_markup_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'minimum_profit' => ['required', 'numeric', 'min:0'],
            'active' => ['nullable', 'boolean'],
            'remark' => ['nullable', 'string'],
        ]);

        $validated['active'] = $request->has('active');

        $deliveryZone->update($validated);

        return redirect()
            ->route('delivery-zones.index')
            ->with('success', 'แก้ไขโซนจัดส่งเรียบร้อยแล้ว');
    }

    public function destroy(DeliveryZone $deliveryZone)
    {
        //
    }
}
