<?php

declare(strict_types=1);

/**
 * Prepare the Laravel runtime directories before requirements are evaluated.
 *
 * This intentionally uses 0775 rather than 0777. If the PHP/web-server process
 * does not own the uploaded tree and cannot chmod/create these paths, the
 * installer will still fail closed and report the affected directory.
 *
 * The optional base path exists for regression testing; production callers use
 * installer_base_path().
 *
 * @return array<string,bool>
 */
function installer_prepare_runtime_directories(?string $base = null): array
{
    $base ??= installer_base_path();
    $base = rtrim($base, '/\\');
    $paths = [
        'storage',
        'storage/app',
        'storage/app/private',
        'storage/app/public',
        'storage/framework',
        'storage/framework/cache',
        'storage/framework/cache/data',
        'storage/framework/sessions',
        'storage/framework/views',
        'storage/logs',
        'bootstrap/cache',
    ];

    $results = [];
    foreach ($paths as $relative) {
        $absolute = $base.'/'.$relative;
        $ok = true;

        if (! is_dir($absolute)) {
            $ok = @mkdir($absolute, 0775, true) || is_dir($absolute);
        }

        if ($ok) {
            @chmod($absolute, 0775);
            clearstatcache(true, $absolute);
            $ok = is_dir($absolute) && is_writable($absolute);
        }

        if ($ok) {
            $probe = $absolute.'/.private-gather-write-probe-'.bin2hex(random_bytes(6));
            $written = @file_put_contents($probe, 'ok', LOCK_EX);
            $ok = $written === 2;
            if (is_file($probe)) {
                @unlink($probe);
            }
        }

        $results[$relative] = $ok;
    }

    return $results;
}
