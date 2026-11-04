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
        $basename = basename($lower);

        // Environment variants and Composer credential files are deployment
        // secrets/state, not application release content. Protect every nested
        // occurrence so a package cannot replace `.env.production`, an old
        // `.env.backup`, or an `auth.json` credential file by choosing a less
        // obvious directory.
        if (
            $basename === '.env'
            || str_starts_with($basename, '.env.')
            || $basename === 'auth.json'
        ) {
            throw new InvalidArgumentException("Upgrade packages may not replace protected file: {$path}");
        }

        $blockedExact = [
            'database/database.sqlite',
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
            '.git/',
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
