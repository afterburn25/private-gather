<?php

declare(strict_types=1);

namespace App\Support;

final class MountUrl
{
    private const ALLOWED_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    public static function to(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        // URL parsers in browsers may normalize embedded control characters.
        // Refuse them before examining a scheme so values such as a tab- or
        // newline-obfuscated javascript: URL never reach a rendered attribute.
        if (preg_match('/[\x00-\x1F\x7F]/', $value)) {
            return '';
        }

        if (str_starts_with($value, '//')) {
            return $value;
        }

        if (preg_match('/^([A-Za-z][A-Za-z0-9+.-]*):/', $value, $match)) {
            return in_array(strtolower($match[1]), self::ALLOWED_SCHEMES, true)
                ? $value
                : '';
        }

        if (! str_starts_with($value, '/')) {
            return $value;
        }

        $base = '';
        try {
            $base = (string) request()->getBaseUrl();
        } catch (\Throwable) {
        }

        return rtrim($base, '/').'/'.ltrim($value, '/');
    }
}
