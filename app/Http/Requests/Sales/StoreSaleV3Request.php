<?php

namespace App\Http\Requests\Sales;

class StoreSaleV3Request extends StoreSaleV2Request
{
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['delivery_type'] = ['required', 'in:delivery,pickup'];
        $rules['delivery_date'] = ['nullable', 'date_format:Y-m-d'];
        $rules['items.*.price_was_edited'] = ['nullable', 'boolean'];
        $rules['items.*.price_changed_since_hold'] = ['nullable', 'boolean'];
        $rules['items.*.original_price'] = ['prohibited'];
        $rules['items.*.price_override_flag'] = ['prohibited'];

        return $rules;
    }
}
