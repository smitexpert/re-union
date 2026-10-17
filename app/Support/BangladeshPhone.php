<?php

namespace App\Support;

class BangladeshPhone
{
    /**
     * Normalize a Bangladesh phone number to bd-bulksms format (8801XXXXXXXXX, 13 digits).
     * Returns null when the input is unusable.
     */
    public static function toE164(string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '880') && strlen($digits) === 13) {
            return $digits;
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            return '880' . substr($digits, 1);
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '1')) {
            return '880' . $digits;
        }

        return null;
    }
}