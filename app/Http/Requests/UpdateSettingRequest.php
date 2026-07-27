<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'owner';
    }

    public function rules(): array
    {
        return [
            'store_name' => ['nullable', 'string', 'max:255'],
            'store_address' => ['nullable', 'string', 'max:2000'],
            'store_phone' => ['nullable', 'string', 'max:50'],
            'tax_number' => ['nullable', 'string', 'max:30'],
            'branch_type' => ['required', 'string', Rule::in(['head_office', 'branch'])],
            'branch_number' => ['nullable', 'string', 'max:30'],
            'logo_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'qr_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }
}
