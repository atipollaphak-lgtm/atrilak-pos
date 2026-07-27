<?php

namespace App\Http\Requests\DailyPaymentClosings;

use Illuminate\Foundation\Http\FormRequest;

class FinalizeDailyPaymentClosingRequest extends FormRequest
{
    public function rules(): array
    {
        return ['revision' => ['required', 'integer', 'min:1']];
    }
}
