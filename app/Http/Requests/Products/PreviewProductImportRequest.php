<?php

namespace App\Http\Requests\Products;

use Illuminate\Foundation\Http\FormRequest;

class PreviewProductImportRequest extends FormRequest
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
                'extensions:xlsx',
                'mimes:xlsx',
                'max:'.config('product_import.max_file_size_kb'),
            ],
        ];
    }
}
