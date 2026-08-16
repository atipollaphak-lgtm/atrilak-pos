<?php

namespace App\Http\Requests\Customers;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmCustomerImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'uuid'],
            'selected_rows' => ['required', 'array', 'min:1'],
            'selected_rows.*' => ['integer', 'distinct', 'min:1'],
        ];
    }
}
