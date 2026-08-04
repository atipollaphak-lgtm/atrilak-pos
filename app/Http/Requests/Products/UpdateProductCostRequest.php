<?php

namespace App\Http\Requests\Products;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductCostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'current_cost_price' => $this->trimmed('current_cost_price'),
            'cost_price' => $this->trimmed('cost_price'),
            'reason' => $this->trimmed('reason'),
        ]);
    }

    public function rules(): array
    {
        return [
            'current_cost_price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'cost_price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    private function trimmed(string $key): mixed
    {
        $value = $this->input($key);

        return is_string($value) ? trim($value) : $value;
    }
}
