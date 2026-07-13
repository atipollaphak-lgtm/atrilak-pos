<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerDeliveryAddress;
use App\Models\DeliveryZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerDeliveryAddressController extends Controller
{
    public function index(Customer $customer)
    {
        $addresses = $customer->deliveryAddresses()
            ->with('deliveryZone')
            ->orderByDesc('is_default')
            ->latest()
            ->get();

        return view('customers.delivery-addresses.index', compact(
            'customer',
            'addresses'
        ));
    }

    public function create(Customer $customer)
    {
        $deliveryZones = DeliveryZone::query()
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('customers.delivery-addresses.create', compact(
            'customer',
            'deliveryZones'
        ));
    }

    public function store(Request $request, Customer $customer)
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
            'receiver_name' => 'nullable|string|max:255',
            'receiver_phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'delivery_zone_id' => 'nullable|exists:delivery_zones,id',
            'landmark' => 'nullable|string|max:255',
            'remark' => 'nullable|string',
        ]);

        DB::transaction(function () use ($request, $customer) {

            if ($request->boolean('is_default')) {
                $customer->deliveryAddresses()->update([
                    'is_default' => false,
                ]);
            }

            $customer->deliveryAddresses()->create([
                'name'             => $request->name,
                'receiver_name'    => $request->receiver_name,
                'receiver_phone'   => $request->receiver_phone,
                'address' => $request->address ?? null,
                'delivery_zone_id' => $request->delivery_zone_id,
                'landmark'         => $request->landmark,
                'remark'           => $request->remark,
                'is_default'       => $request->boolean('is_default'),
            ]);
        });

        return redirect()
            ->route('customers.delivery-addresses.index', $customer)
            ->with('success', 'เพิ่มที่อยู่จัดส่งเรียบร้อย');
    }

    public function edit(
        Customer $customer,
        CustomerDeliveryAddress $deliveryAddress
    ) {
        $deliveryZones = DeliveryZone::query()
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $address = $deliveryAddress;

        return view(
            'customers.delivery-addresses.edit',
            compact(
                'customer',
                'address',
                'deliveryZones'
            )
        );
    }

    public function update(
        Request $request,
        Customer $customer,
        CustomerDeliveryAddress $deliveryAddress
    ) {
        $request->validate([
            'name' => 'nullable|string|max:255',
            'receiver_name' => 'nullable|string|max:255',
            'receiver_phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'delivery_zone_id' => 'nullable|exists:delivery_zones,id',
            'landmark' => 'nullable|string|max:255',
            'remark' => 'nullable|string',
        ]);

        DB::transaction(function () use ($request, $customer, $deliveryAddress) {

            if ($request->boolean('is_default')) {
                $customer->deliveryAddresses()->update([
                    'is_default' => false,
                ]);
            }

            $deliveryAddress->update([
                'name'             => $request->name,
                'receiver_name'    => $request->receiver_name,
                'receiver_phone'   => $request->receiver_phone,
                'address' => $request->address ?? null,
                'delivery_zone_id' => $request->delivery_zone_id,
                'landmark'         => $request->landmark,
                'remark'           => $request->remark,
                'is_default'       => $request->boolean('is_default'),
            ]);
        });

        return redirect()
            ->route('customers.delivery-addresses.index', $customer)
            ->with('success', 'แก้ไขที่อยู่จัดส่งเรียบร้อย');
    }

    public function getByCustomer(Customer $customer)
{
    $addresses = $customer->deliveryAddresses()
        ->with('deliveryZone')
        ->orderByDesc('is_default')
        ->orderBy('name')
        ->get();

    return response()->json($addresses);
}

    public function destroy(
        Customer $customer,
        CustomerDeliveryAddress $deliveryAddress
    ) {
        DB::transaction(function () use ($deliveryAddress) {
            $deliveryAddress->delete();
        });

        return redirect()
            ->route('customers.delivery-addresses.index', $customer)
            ->with('success', 'ลบที่อยู่จัดส่งเรียบร้อย');
    }
}
