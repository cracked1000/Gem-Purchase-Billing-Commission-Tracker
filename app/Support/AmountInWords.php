<?php

namespace App\Support;

use NumberToWords\NumberToWords;

class AmountInWords
{
    /** "Four hundred five thousand rupees only" */
    public static function convert(float|int|string|null $amount, string $currency = 'rupees'): string
    {
        $number = (int) round((float) ($amount ?? 0));

        if ($number <= 0) {
            return 'Zero ' . $currency . ' only';
        }

        $words = (new NumberToWords())->getNumberTransformer('en')->toWords($number);

        return ucfirst($words) . ' ' . $currency . ' only';
    }
}
