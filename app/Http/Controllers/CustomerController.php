<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\DeliveryZone;
use App\Services\CustomerService;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        $sort = $request->input('sort', 'zone');
        $direction = $request->input('direction', 'asc');
        $allowedSorts = ['zone', 'name', 'created_at'];
        $sort = in_array($sort, $allowedSorts, true) ? $sort : 'zone';
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'asc';

        $customers = Customer::query()
            ->with(['defaultDeliveryAddress.deliveryZone'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhereHas('deliveryAddresses', function ($addressQuery) use ($search): void {
                            $addressQuery->where('address', 'like', "%{$search}%")
                                ->orWhere('receiver_phone', 'like', "%{$search}%")
                                ->orWhereHas('deliveryZone', function ($zoneQuery) use ($search): void {
                                    $zoneQuery->where('name', 'like', "%{$search}%");
                                });
                        });
                });
            });

        if ($sort === 'zone') {
            $customers->orderByRaw('(select dz.sort_order from customer_delivery_addresses cda join delivery_zones dz on dz.id = cda.delivery_zone_id where cda.customer_id = customers.id and cda.is_default = true limit 1) is null asc')
                ->orderByRaw('(select dz.sort_order from customer_delivery_addresses cda join delivery_zones dz on dz.id = cda.delivery_zone_id where cda.customer_id = customers.id and cda.is_default = true limit 1) '.$direction)
                ->orderBy('name', 'asc');
        } else {
            $customers->orderBy($sort, $direction);
        }

        $customers = $customers->paginate(15)->withQueryString();
        $deliveryZones = $this->deliveryZones();

        return view('customers.index', compact('customers', 'deliveryZones', 'search', 'sort', 'direction'));
    }

    public function create()
    {
        return view('customers.create', ['deliveryZones' => $this->deliveryZones()]);
    }

    public function store(StoreCustomerRequest $request, CustomerService $service)
    {
        $customer = $service->create($request->validated());

        return redirect()->route('customers.show', $customer)
            ->with('success', 'สร้างลูกค้าและที่อยู่จัดส่งหลักเรียบร้อยแล้ว');
    }

    public function show(Customer $customer)
    {
        $customer->load(['deliveryAddresses.deliveryZone', 'defaultDeliveryAddress.deliveryZone']);
        $sales = $customer->sales()->latest('sale_date')->latest('id')->paginate(10, ['*'], 'sales_page')->withQueryString();

        return view('customers.show', compact('customer', 'sales'));
    }

    public function edit(Customer $customer)
    {
        $customer->load('defaultDeliveryAddress');

        return view('customers.edit', [
            'customer' => $customer,
            'primaryAddress' => $customer->defaultDeliveryAddress,
            'deliveryZones' => $this->deliveryZones(),
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer, CustomerService $service)
    {
        $service->update($customer, $request->validated());

        return redirect()->route('customers.show', $customer)
            ->with('success', 'บันทึกข้อมูลลูกค้าเรียบร้อยแล้ว');
    }

    public function destroy(Customer $customer)
    {
        $customer->update(['active' => false]);

        return redirect()->route('customers.show', $customer)
            ->with('success', 'ปิดใช้งานลูกค้าเรียบร้อยแล้ว');
    }

    public function restore(Customer $customer)
    {
        $customer->update(['active' => true]);

        return redirect()->route('customers.show', $customer)
            ->with('success', 'เปิดใช้งานลูกค้าเรียบร้อยแล้ว');
    }

    private function deliveryZones()
    {
        return DeliveryZone::query()
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
