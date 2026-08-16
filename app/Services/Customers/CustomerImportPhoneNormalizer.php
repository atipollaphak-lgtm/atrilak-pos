<?php

namespace App\Services\Customers;

use App\Data\Customers\CustomerImportPhoneResultData;

class CustomerImportPhoneNormalizer
{
    public function normalize(mixed $value): CustomerImportPhoneResultData
    {
        $raw = $this->stringValue($value);

        if ($raw === '') {
            return new CustomerImportPhoneResultData(null, 'blank');
        }

        $digits = preg_replace('/[\s\-\(\)]/u', '', $this->toArabicDigits($raw)) ?? '';

        if ($digits === '') {
            return new CustomerImportPhoneResultData(null, 'blank');
        }

        if (preg_match('/^\d+$/D', $digits) !== 1) {
            return new CustomerImportPhoneResultData(
                phone: null,
                status: 'review_required',
                warnings: ['รูปแบบเบอร์โทรไม่เป็นตัวเลขที่ตรวจสอบได้'],
            );
        }

        if (preg_match('/^0[689]\d{8}$/D', $digits) === 1) {
            return new CustomerImportPhoneResultData($digits, 'valid');
        }

        if (preg_match('/^[689]\d{8}$/D', $digits) === 1) {
            return new CustomerImportPhoneResultData(
                phone: '0'.$digits,
                status: 'normalized',
                warnings: ['เติม 0 ด้านหน้าให้เป็นรูปแบบเบอร์มือถือไทย'],
            );
        }

        return new CustomerImportPhoneResultData(
            phone: $digits,
            status: 'review_required',
            warnings: ['รูปแบบหรือความยาวเบอร์โทรต้องตรวจสอบเพิ่มเติม'],
        );
    }

    /**
     * @return list<string>
     */
    public function findEmbeddedPhones(string $text): array
    {
        $text = $this->toArabicDigits($text);
        preg_match_all('/0[689](?:[\s\-]?\d){8}/u', $text, $matches);

        $phones = array_map(
            static fn (string $phone): string => preg_replace('/[\s\-]/', '', $phone) ?? $phone,
            $matches[0] ?? [],
        );

        return array_values(array_unique($phones));
    }

    private function stringValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_float($value) && is_finite($value) && floor($value) === $value) {
            return trim(number_format($value, 0, '.', ''));
        }

        return trim((string) $value);
    }

    private function toArabicDigits(string $value): string
    {
        return strtr($value, [
            '๐' => '0',
            '๑' => '1',
            '๒' => '2',
            '๓' => '3',
            '๔' => '4',
            '๕' => '5',
            '๖' => '6',
            '๗' => '7',
            '๘' => '8',
            '๙' => '9',
        ]);
    }
}
