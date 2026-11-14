<?php

namespace App\Helpers;

class SecurityHelper
{
    /**
     * Sanitize a cell value for CSV export to prevent CSV Formula Injection (CWE-1236).
     * If the string begins with =, +, -, @, \t, or \r, prepend a single quote (').
     */
    public static function sanitizeCsvCell(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        // Check if raw string begins with any trigger character (including tab/carriage return)
        if (in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        // Also check if after stripping whitespace the first character is a formula trigger
        $trimmed = ltrim($value, " \t\r\n\0\x0B");
        if ($trimmed !== '' && in_array($trimmed[0], ['=', '+', '-', '@'], true)) {
            return "'".$value;
        }

        return $value;
    }

    /**
     * Sanitize an entire row of CSV values.
     */
    public static function sanitizeCsvRow(array $row): array
    {
        return array_map([self::class, 'sanitizeCsvCell'], $row);
    }
}
