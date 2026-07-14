<?php

namespace App\Http\Requests\Sales;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Closure;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;

class StoreSaleV2Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $content = trim($this->getContent());

        if ($this->isJson() && $content !== '' && ! json_validate($content)) {
            throw new HttpResponseException(response()->json([
                'message' => 'รูปแบบ JSON ไม่ถูกต้อง',
                'errors' => [
                    'json' => ['ไม่สามารถอ่านข้อมูล JSON ที่ส่งมาได้'],
                ],
            ], 400));
        }
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'customer_delivery_address_id' => ['nullable', 'integer', 'exists:customer_delivery_addresses,id'],
            'technician_id' => ['nullable', 'integer', 'exists:technicians,id'],
            'sale_date' => ['nullable', 'date_format:Y-m-d'],
            'delivery_type' => ['nullable', 'in:delivery,pickup'],
            'delivery_zone_id' => ['nullable', 'integer'],
            'discount' => ['nullable', $this->decimalRule(2, 10, false)],
            'delivery_fee' => ['nullable', $this->decimalRule(2, 10, false)],
            'base_qty' => ['prohibited'],
            'conversion_rate_used' => ['prohibited'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['bail', 'required', 'integer', 'exists:products,id'],
            'items.*.product_unit_id' => ['nullable', 'integer', 'exists:product_units,id'],
            'items.*.qty' => ['bail', 'required', $this->decimalRule(2, 13, true)],
            'items.*.selling_price' => ['bail', 'required', $this->decimalRule(2, 13, true)],
            'items.*.base_qty' => ['prohibited'],
            'items.*.conversion_rate_used' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $customerId = $this->input('customer_id');
            $addressId = $this->input('customer_delivery_address_id');

            if (! $this->isPositiveInteger($customerId) || ! $this->isPositiveInteger($addressId)) {
                return;
            }

            $belongsToCustomer = DB::table('customer_delivery_addresses')
                ->where('id', $addressId)
                ->where('customer_id', $customerId)
                ->exists();

            if (! $belongsToCustomer) {
                $validator->errors()->add(
                    'customer_delivery_address_id',
                    'ที่อยู่จัดส่งไม่ตรงกับลูกค้าที่เลือก'
                );
            }
        });
    }

    protected function failedValidation(ValidatorContract $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'ข้อมูลการขายไม่ถูกต้อง',
            'errors' => $validator->errors(),
        ], 422));
    }

    private function decimalRule(int $scale, int $integerDigits, bool $strictlyPositive): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($scale, $integerDigits, $strictlyPositive): void {
            if ($value === null || $value === '') {
                return;
            }

            $decimal = is_int($value) || is_float($value) || is_string($value)
                ? (string) $value
                : '';

            if (! preg_match('/^\d{1,'.$integerDigits.'}(?:\.\d{1,'.$scale.'})?$/D', $decimal)) {
                $fail('รูปแบบ :attribute ไม่ถูกต้องหรือมีทศนิยมเกิน '.$scale.' ตำแหน่ง');

                return;
            }

            try {
                $number = BigDecimal::of($decimal);
            } catch (MathException) {
                $fail('รูปแบบ :attribute ไม่ถูกต้อง');

                return;
            }

            if ($strictlyPositive ? $number->isLessThanOrEqualTo(0) : $number->isLessThan(0)) {
                $fail($strictlyPositive
                    ? ':attribute ต้องมากกว่า 0'
                    : ':attribute ต้องไม่น้อยกว่า 0');
            }
        };
    }

    private function isPositiveInteger(mixed $value): bool
    {
        return (is_int($value) && $value > 0)
            || (is_string($value) && preg_match('/^[1-9]\d*$/D', $value) === 1);
    }
}
