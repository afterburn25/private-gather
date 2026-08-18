<?php

namespace App\Support;

use InvalidArgumentException;

final class UpgradePath
{
    public static function normalize(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = preg_replace('#/+#', '/', $path) ?? $path;
        $path = ltrim($path, '/');

        if (preg_match('/^[A-Za-z]:\//', $path)) {
            throw new InvalidArgumentException("Absolute drive paths are not allowed: {$path}");
        }

        if ($path === '' || str_contains($path, "\0")) {
            throw new InvalidArgumentException('Upgrade path is empty or invalid.');
        }

        $parts = explode('/', $path);
        foreach ($parts as $part) {
            if ($part === '' || $part === '.' || $part === '..') {
                throw new InvalidArgumentException("Unsafe upgrade path: {$path}");
            }
        }

        self::assertAllowed($path);

        return $path;
    }

    public static function assertAllowed(string $path): void
    {
        $lower = strtolower($path);

        $blockedExact = [
            '.env',
            '.env.example',
            'storage/app/installed.lock',
            'storage/app/install-record.json',
        ];

        foreach ($blockedExact as $blocked) {
            if ($lower === $blocked) {
                throw new InvalidArgumentException("Upgrade packages may not replace protected file: {$path}");
            }
        }

        $blockedPrefixes = [
            'storage/',
            'install/',
            '.install-disabled-',
            'public/uploads/',
            'public/storage/',
        ];

        foreach ($blockedPrefixes as $prefix) {
            if (str_starts_with($lower, $prefix)) {
                throw new InvalidArgumentException("Upgrade packages may not write protected path: {$path}");
            }
        }
    }
}
