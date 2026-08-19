<?php

declare(strict_types=1);

namespace App\Support;

final class CsvCell
{
    public static function safe(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        // Spreadsheet applications may interpret CSV cells beginning with
        // =, +, -, or @ as formulas. Leading whitespace/control characters can
        // also be used to disguise those prefixes. An apostrophe forces the
        // cell to be treated as text when the CSV is opened interactively.
        $candidate = ltrim($value, " \t\r\n");
        if ($candidate !== '' && in_array($candidate[0], ['=', '+', '-', '@'], true)) {
            return "'".$value;
        }

        // A leading tab/newline is itself a known spreadsheet-injection
        // technique even when the following character is not visible here.
        if (preg_match('/^[\t\r\n]/', $value)) {
            return "'".$value;
        }

        return $value;
    }
}
