<?php

namespace App\Http\Requests\DailyPaymentClosings;

use Illuminate\Foundation\Http\FormRequest;

class ReopenDailyPaymentClosingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'not_regex:/^\s*$/'],
            'revision' => ['required', 'integer', 'min:1'],
        ];
    }
}
