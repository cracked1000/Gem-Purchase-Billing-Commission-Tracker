<?php

namespace App\Support;

class Lkr
{
    /** Format an amount as "LKR 1,234,567.89". */
    public static function format(float|int|string|null $amount): string
    {
        return 'LKR ' . number_format((float) ($amount ?? 0), 2);
    }

    /** Compact format used on dashboard cards, e.g. "LKR 1.25M". */
    public static function compact(float|int|string|null $amount): string
    {
        $n = (float) ($amount ?? 0);

        return match (true) {
            $n >= 1_000_000_000 => 'LKR ' . self::trim($n / 1_000_000_000) . 'B',
            $n >= 1_000_000     => 'LKR ' . self::trim($n / 1_000_000) . 'M',
            $n >= 1_000         => 'LKR ' . self::trim($n / 1_000) . 'K',
            default             => 'LKR ' . number_format($n, 2),
        };
    }

    private static function trim(float $n): string
    {
        $s = number_format($n, 2);

        return rtrim(rtrim($s, '0'), '.');
    }
}
