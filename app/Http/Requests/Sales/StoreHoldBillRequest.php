<?php

namespace App\Http\Requests\Sales;

use App\Models\CustomerDeliveryAddress;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Validator;

class StoreHoldBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'customer_delivery_address_id' => ['nullable', 'integer', 'exists:customer_delivery_addresses,id'],
            'delivery_zone_id' => ['nullable', 'integer', 'exists:delivery_zones,id'],
            'delivery_zone_name_snapshot' => ['nullable', 'string', 'max:255'],
            'delivery_zone_markup_percent_snapshot' => ['nullable', 'numeric', 'min:0'],
            'delivery_zone_rounding_increment_snapshot' => ['nullable', 'numeric', 'min:0'],
            'delivery_zone_minimum_profit_snapshot' => ['nullable', 'numeric', 'min:0'],
            'sale_date' => ['required', 'date_format:Y-m-d'],
            'delivery_type' => ['required', 'in:delivery,pickup'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['required', 'array'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.product_unit_id' => ['nullable', 'integer', 'exists:product_units,id'],
            'items.*.qty' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999999.99'],
            'items.*.selling_price' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999999.99'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $customerId = $this->integer('customer_id');
            $addressId = $this->integer('customer_delivery_address_id');

            if ($addressId && (! $customerId || ! CustomerDeliveryAddress::query()
                ->whereKey($addressId)
                ->where('customer_id', $customerId)
                ->exists())) {
                $validator->errors()->add(
                    'customer_delivery_address_id',
                    'ที่อยู่จัดส่งไม่ตรงกับลูกค้าที่เลือก'
                );
            }
        });
    }

    protected function failedValidation(ValidatorContract $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'ข้อมูลพักบิลไม่ถูกต้อง',
            'errors' => $validator->errors(),
        ], 422));
    }
}
