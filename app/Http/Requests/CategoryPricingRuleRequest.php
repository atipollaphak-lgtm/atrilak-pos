<?php

namespace App\Http\Requests;

use App\Services\Pricing\PricingService;
use Illuminate\Foundation\Http\FormRequest;

class CategoryPricingRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'owner';
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'pricing_method' => ['required', 'in:percentage,fixed'],
            'pricing_value' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'rounding_direction' => ['nullable', 'in:up,down,nearest'],
            'rounding_unit' => ['nullable', 'in:'.implode(',', PricingService::ROUNDING_UNITS)],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
