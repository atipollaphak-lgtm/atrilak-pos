<?php

namespace App\Http\Requests\Sales;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

class VoidSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'void_reason' => [
                'required',
                'string',
                'max:1000',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && trim($value) === '') {
                        $fail('กรุณาระบุเหตุผลการยกเลิกใบขาย');
                    }
                },
            ],
        ];
    }
}
