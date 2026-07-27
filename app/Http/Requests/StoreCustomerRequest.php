<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'delivery_zone_id' => ['nullable', Rule::exists('delivery_zones', 'id')->where('active', true)],
            'address' => ['nullable', 'string', 'max:5000'],
            'receiver_phone' => ['nullable', 'string', 'max:50'],
            'use_customer_phone' => ['nullable', 'boolean'],
            'remark' => ['nullable', 'string', 'max:5000'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'branch_type' => ['nullable', Rule::in(['สำนักงานใหญ่', 'สาขา'])],
            'branch_number' => ['nullable', 'string', 'max:5'],
        ];
    }
}
