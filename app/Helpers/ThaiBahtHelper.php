<?php

if (!function_exists('thaiBahtText')) {
    function thaiBahtText($amount)
    {
        $amount = number_format($amount, 2, '.', '');
        [$baht, $satang] = explode('.', $amount);

        $txtnum1 = [
            '',
            'หนึ่ง',
            'สอง',
            'สาม',
            'สี่',
            'ห้า',
            'หก',
            'เจ็ด',
            'แปด',
            'เก้า'
        ];

        $txtnum2 = [
            '',
            'สิบ',
            'ร้อย',
            'พัน',
            'หมื่น',
            'แสน',
            'ล้าน'
        ];

        $convert = function ($number) use ($txtnum1, $txtnum2) {
            $number = (string) intval($number);
            $length = strlen($number);
            $result = '';

            for ($i = 0; $i < $length; $i++) {
                $n = intval($number[$i]);
                $position = $length - $i - 1;

                if ($n == 0) {
                    continue;
                }

                if ($position == 0 && $n == 1 && $length > 1) {
                    $result .= 'เอ็ด';
                } elseif ($position == 1 && $n == 2) {
                    $result .= 'ยี่';
                } elseif ($position == 1 && $n == 1) {
                    $result .= '';
                } else {
                    $result .= $txtnum1[$n];
                }

                $result .= $txtnum2[$position];
            }

            return $result;
        };

        $bahtText = $convert($baht) . 'บาท';

        if (intval($satang) == 0) {
            return $bahtText . 'ถ้วน';
        }

        return $bahtText . $convert($satang) . 'สตางค์';
    }
}
