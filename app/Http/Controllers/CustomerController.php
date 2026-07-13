<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerDeliveryAddress;
use App\Models\DeliveryZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    public function index()
    {
        $customers = Customer::latest()->get();

        $deliveryZones = DeliveryZone::query()
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view(
            'customers.index',
            compact(
                'customers',
                'deliveryZones'
            )
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
        ]);

        DB::transaction(function () use ($request) {

                       $customer = Customer::create([
                'code' => $request->code,
                'name' => $request->name,
                'phone' => $request->phone,
                'address' => $request->address,
                'tax_number' => $request->tax_number,
                'branch_type' => $request->branch_type ?? 'สำนักงานใหญ่',
                'branch_number' => $request->branch_number,
                'remark' => $request->remark,
                'active' => $request->active ?? 1,
            ]);

            if ($request->filled('delivery_address')) {

                CustomerDeliveryAddress::create([
                    'customer_id'      => $customer->id,
                    'name'             => $request->delivery_name ?: 'บ้าน',
                    'receiver_name'    => $request->receiver_name,
                    'receiver_phone'   => $request->receiver_phone,
                    'address'          => $request->delivery_address,
                    'landmark'         => $request->landmark,
                    'delivery_zone_id' => $request->delivery_zone_id ?: null,
                    'is_default'       => true,
                    'remark'           => $request->delivery_remark,
                ]);
            }
        });

        return back()->with(
            'success',
            'เพิ่มลูกค้าเรียบร้อย'
        );
    }
    public function edit(Customer $customer)
    {
        return view(
            'customers.edit',
            compact('customer')
        );
    }
    public function update(
        Request $request,
        Customer $customer
    ) {
        $request->validate([
            'name' => 'required',
        ]);

                $customer->update([
            'code' => $request->code,
            'name' => $request->name,
            'phone' => $request->phone,
            'address' => $request->address,
            'tax_number' => $request->tax_number,
            'branch_type' => $request->branch_type ?? 'สำนักงานใหญ่',
            'branch_number' => $request->branch_number,
            'remark' => $request->remark,
            'active' => $request->active ?? 1,
        ]);

        return back()->with(
            'success',
            'แก้ไขลูกค้าเรียบร้อย'
        );
    }

    public function destroy(Customer $customer)
    {
        $customer->active = false;

        $customer->save();

        return back()->with(
            'success',
            'ปิดใช้งานลูกค้าเรียบร้อย'
        );
    }
    public function restore(Customer $customer)
    {
        $customer->active = true;

        $customer->save();

        return back()->with(
            'success',
            'เปิดใช้งานลูกค้าเรียบร้อย'
        );
    }
}
