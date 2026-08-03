<?php

namespace App\Http\Requests\Receivings;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmReceiveStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'preview_token' => ['required', 'string', 'size:64'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }
}
