<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
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
            'delivery_zone_id' => ['required', Rule::exists('delivery_zones', 'id')->where('active', true)],
            'address' => ['nullable', 'string', 'max:5000'],
            'receiver_name' => ['nullable', 'string', 'max:255'],
            'receiver_phone' => ['nullable', 'string', 'max:50'],
            'use_customer_phone' => ['nullable', 'boolean'],
            'remark' => ['nullable', 'string', 'max:5000'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'branch_type' => ['nullable', Rule::in(['สำนักงานใหญ่', 'สาขา'])],
            'branch_number' => ['nullable', 'string', 'max:5'],
        ];
    }

    public function messages(): array
    {
        return [
            'delivery_zone_id.required' => 'กรุณาเลือกโซนลูกค้า',
            'delivery_zone_id.exists' => 'กรุณาเลือกโซนลูกค้า',
        ];
    }

    protected function failedValidation(ValidatorContract $validator): void
    {
        if ($this->expectsJson()) {
            throw new HttpResponseException(response()->json([
                'message' => 'ข้อมูลลูกค้าไม่ถูกต้อง',
                'errors' => $validator->errors()->toArray(),
            ], 422));
        }

        parent::failedValidation($validator);
    }
}
