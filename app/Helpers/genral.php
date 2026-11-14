<?php

use App\Helpers\SecurityHelper;

if (! function_exists('sanitize_csv_cell')) {
    function sanitize_csv_cell(mixed $value): mixed
    {
        return SecurityHelper::sanitizeCsvCell($value);
    }
}

if (! function_exists('sanitize_csv_row')) {
    function sanitize_csv_row(array $row): array
    {
        return SecurityHelper::sanitizeCsvRow($row);
    }
}
