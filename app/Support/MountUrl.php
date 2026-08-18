<?php

declare(strict_types=1);

namespace App\Support;

final class MountUrl
{
    public static function to(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') return '';
        if (preg_match('#^(?:https?:)?//#i', $value) || preg_match('~^(?:mailto:|tel:|data:|#)~i', $value)) return $value;
        if (! str_starts_with($value, '/')) return $value;

        $base = '';
        try { $base = (string) request()->getBaseUrl(); } catch (\Throwable) {}
        return rtrim($base, '/').'/'.ltrim($value, '/');
    }
}
