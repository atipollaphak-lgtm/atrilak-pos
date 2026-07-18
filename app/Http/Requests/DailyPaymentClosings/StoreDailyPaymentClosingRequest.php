<?php

namespace App\Http\Requests\DailyPaymentClosings;

use Illuminate\Foundation\Http\FormRequest;

class StoreDailyPaymentClosingRequest extends FormRequest
{
    public function rules(): array
    {
        return ['business_date' => ['required', 'date_format:Y-m-d']];
    }
}
