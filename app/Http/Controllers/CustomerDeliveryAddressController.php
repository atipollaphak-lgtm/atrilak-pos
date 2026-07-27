<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerDeliveryAddressRequest;
use App\Http\Requests\UpdateCustomerDeliveryAddressRequest;
use App\Models\Customer;
use App\Models\CustomerDeliveryAddress;
use App\Models\DeliveryZone;
use App\Services\CustomerDeliveryAddressService;
use RuntimeException;

class CustomerDeliveryAddressController extends Controller
{
    public function index(Customer $customer)
    {
        $addresses = $customer->deliveryAddresses()->with('deliveryZone')->orderByDesc('is_default')->oldest('id')->get();

        return view('customers.delivery-addresses.index', compact('customer', 'addresses'));
    }

    public function create(Customer $customer)
    {
        return view('customers.delivery-addresses.create', [
            'customer' => $customer,
            'deliveryZones' => $this->deliveryZones(),
        ]);
    }

    public function store(StoreCustomerDeliveryAddressRequest $request, Customer $customer, CustomerDeliveryAddressService $service)
    {
        $service->create($customer, $request->validated());

        return redirect()->route('customers.show', $customer)->with('success', 'เพิ่มที่อยู่จัดส่งเรียบร้อยแล้ว');
    }

    public function edit(Customer $customer, CustomerDeliveryAddress $deliveryAddress)
    {
        $this->assertBelongsTo($customer, $deliveryAddress);

        return view('customers.delivery-addresses.edit', [
            'customer' => $customer,
            'address' => $deliveryAddress,
            'deliveryZones' => $this->deliveryZones(),
        ]);
    }

    public function update(UpdateCustomerDeliveryAddressRequest $request, Customer $customer, CustomerDeliveryAddress $deliveryAddress, CustomerDeliveryAddressService $service)
    {
        $service->update($customer, $deliveryAddress, $request->validated());

        return redirect()->route('customers.show', $customer)->with('success', 'แก้ไขที่อยู่จัดส่งเรียบร้อยแล้ว');
    }

    public function setPrimary(Customer $customer, CustomerDeliveryAddress $deliveryAddress, CustomerDeliveryAddressService $service)
    {
        $service->setPrimary($customer, $deliveryAddress);

        return redirect()->route('customers.show', $customer)->with('success', 'ตั้งเป็นที่อยู่หลักเรียบร้อยแล้ว');
    }

    public function getByCustomer(Customer $customer)
    {
        return response()->json($customer->deliveryAddresses()->with('deliveryZone')->orderByDesc('is_default')->oldest('id')->get());
    }

    public function destroy(Customer $customer, CustomerDeliveryAddress $deliveryAddress, CustomerDeliveryAddressService $service)
    {
        try {
            $service->delete($customer, $deliveryAddress);
        } catch (RuntimeException $exception) {
            return redirect()->route('customers.show', $customer)->with('error', $exception->getMessage());
        }

        return redirect()->route('customers.show', $customer)->with('success', 'ลบที่อยู่จัดส่งเรียบร้อยแล้ว');
    }

    private function deliveryZones()
    {
        return DeliveryZone::query()->where('active', true)->orderBy('sort_order')->orderBy('name')->get();
    }

    private function assertBelongsTo(Customer $customer, CustomerDeliveryAddress $address): void
    {
        abort_unless((int) $address->customer_id === (int) $customer->id, 404);
    }
}
