<?php

namespace App\Http\Requests\DailyPaymentClosings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDailyPaymentClosingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'actual_cash_amount' => ['required', 'string', 'regex:/^\d+\.\d{2}$/'],
            'actual_promptpay_amount' => ['required', 'string', 'regex:/^\d+\.\d{2}$/'],
            'notes' => ['nullable', 'string'],
            'revision' => ['required', 'integer', 'min:1'],
        ];
    }
}
