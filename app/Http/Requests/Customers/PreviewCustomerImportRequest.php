<?php

namespace App\Http\Requests\Customers;

use Illuminate\Foundation\Http\FormRequest;

class PreviewCustomerImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:'.config('customer_import.max_file_size_kb'),
            ],
        ];
    }
}
