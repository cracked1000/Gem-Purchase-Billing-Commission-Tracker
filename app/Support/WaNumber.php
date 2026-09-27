<?php

namespace App\Support;

class WaNumber
{
    /**
     * Normalise a local / international phone number into the digits-only
     * format required by wa.me links (e.g. 0771234567 -> 94771234567).
     * Returns null when there is no usable number.
     */
    public static function normalize(?string $phone, ?string $countryCode = null): ?string
    {
        if (! $phone) {
            return null;
        }

        $countryCode = $countryCode ?: config('gemdesk.whatsapp_country_code', '94');

        // Strip everything except digits and a leading +
        $digits = preg_replace('/\D+/', '', $phone);

        if ($digits === '') {
            return null;
        }

        // 0094... or 094... style international input
        if (str_starts_with($digits, '00')) {
            return ltrim(substr($digits, 2), '0');
        }

        // Local Sri Lankan style: 0XXXXXXXXX (e.g. 0771234567)
        if (str_starts_with($digits, '0')) {
            return $countryCode . ltrim($digits, '0');
        }

        // Already international without + (starts with the country code) or missing leading 0
        if (! str_starts_with($digits, $countryCode) && strlen($digits) === strlen($countryCode) + 9) {
            return $digits; // looks like full international number already
        }

        if (strlen($digits) === 9) {
            return $countryCode . $digits;
        }

        return $digits;
    }

    /** Build a full wa.me click-to-chat link. */
    public static function link(?string $phone, string $message = ''): ?string
    {
        $number = self::normalize($phone);

        if (! $number) {
            return null;
        }

        return 'https://wa.me/' . $number . ($message !== '' ? '?text=' . rawurlencode($message) : '');
    }
}
