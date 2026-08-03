<?php

namespace App\Http\Requests\Receivings;

use Illuminate\Foundation\Http\FormRequest;

class PreviewReceiveStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source' => ['required', 'in:supplier,production'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'purchase_date' => ['required', 'date_format:Y-m-d'],
            'supplier_document_number' => ['nullable', 'string', 'max:100'],
            'remark' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.product_unit_id' => ['nullable', 'integer', 'exists:product_units,id'],
            'items.*.qty' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'items.*.cost_price' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
        ];
    }
}
