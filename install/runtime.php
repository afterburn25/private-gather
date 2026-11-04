<?php

declare(strict_types=1);

require_once __DIR__.'/cleanup.php';

/**
 * Prepare Laravel runtime directories before requirements are evaluated.
 *
 * Normal installs are repaired with mkdir/chmod(0775). If an uploaded runtime
 * directory belongs to a different Unix account and chmod is therefore denied,
 * the installer can rebuild that tree atomically as the PHP/web-server account
 * when its parent directory is writable. Existing files are copied forward,
 * the replacement is write-probed, and the old tree is removed only after the
 * replacement is active.
 *
 * No 0777 fallback is used.
 *
 * @return array<string,bool>
 */
function installer_prepare_runtime_directories(?string $base = null): array
{
    $base ??= installer_base_path();
    $base = rtrim($base, '/\\');

    installer_runtime_ensure_tree($base);

    // storage/ is a top-level Laravel runtime tree. If chmod cannot repair it,
    // rebuild it under the application root so the active PHP user owns it.
    if (! installer_runtime_write_probe($base.'/storage')) {
        installer_atomic_rebuild_tree($base, 'storage');
    }

    // Prefer replacing only bootstrap/cache. If bootstrap/ itself is not
    // writable, rebuild the complete bootstrap tree under the app root.
    if (! installer_runtime_write_probe($base.'/bootstrap/cache')) {
        if (! installer_atomic_rebuild_tree($base, 'bootstrap/cache')) {
            installer_atomic_rebuild_tree($base, 'bootstrap');
        }
    }

    // Recreate required descendants after any ownership-neutral replacement.
    installer_runtime_ensure_tree($base);

    $results = [];
    foreach (installer_runtime_paths() as $relative) {
        $results[$relative] = installer_runtime_write_probe($base.'/'.$relative);
    }

    return $results;
}

/** @return list<string> */
function installer_runtime_paths(): array
{
    return [
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
}

function installer_runtime_ensure_tree(string $base): void
{
    foreach (installer_runtime_paths() as $relative) {
        $absolute = $base.'/'.$relative;
        if (! is_dir($absolute)) {
            @mkdir($absolute, 0775, true);
        }
        if (is_dir($absolute)) {
            @chmod($absolute, 0775);
            clearstatcache(true, $absolute);
        }
    }
}

function installer_runtime_write_probe(string $path): bool
{
    clearstatcache(true, $path);
    if (! is_dir($path) || ! is_writable($path)) {
        return false;
    }

    try {
        $probe = $path.'/.private-gather-write-probe-'.bin2hex(random_bytes(6));
    } catch (Throwable) {
        return false;
    }

    $written = @file_put_contents($probe, 'ok', LOCK_EX);
    $ok = $written === 2;
    if (is_file($probe)) {
        @unlink($probe);
    }

    return $ok;
}

/**
 * Atomically replace an existing directory tree with a PHP-owned copy.
 *
 * A complete staging copy is built before the original name is moved. If the
 * replacement cannot be activated or write-probed, the original tree is
 * restored before this function returns false.
 */
function installer_atomic_rebuild_tree(string $base, string $relative): bool
{
    $base = rtrim($base, '/\\');
    $relative = trim(str_replace('\\', '/', $relative), '/');
    if ($relative === '' || str_contains($relative, '..')) {
        return false;
    }

    $target = $base.'/'.$relative;
    $parent = dirname($target);
    if (! is_dir($target) || ! is_dir($parent) || ! is_writable($parent)) {
        return false;
    }

    try {
        $nonce = bin2hex(random_bytes(6));
    } catch (Throwable) {
        return false;
    }

    $name = basename($target);
    $staging = $parent.'/.'.$name.'-pg-repair-'.$nonce;
    $backup = $parent.'/.'.$name.'-pg-original-'.$nonce;

    if (! @mkdir($staging, 0775, true)) {
        return false;
    }

    if (! installer_copy_tree_for_repair($target, $staging)) {
        installer_best_effort_remove_tree($staging);
        return false;
    }

    @chmod($staging, 0775);
    if (! installer_runtime_write_probe($staging)) {
        installer_best_effort_remove_tree($staging);
        return false;
    }

    if (! @rename($target, $backup)) {
        installer_best_effort_remove_tree($staging);
        return false;
    }

    if (! @rename($staging, $target)) {
        @rename($backup, $target);
        installer_best_effort_remove_tree($staging);
        return false;
    }

    if (! installer_runtime_write_probe($target)) {
        $failed = $parent.'/.'.$name.'-pg-failed-'.$nonce;
        @rename($target, $failed);
        @rename($backup, $target);
        installer_best_effort_remove_tree($failed);
        return false;
    }

    // The replacement is active and writeable. Removing the original backup is
    // best-effort because the old upload ownership may itself prevent cleanup.
    installer_best_effort_remove_tree($backup);

    return true;
}

function installer_copy_tree_for_repair(string $source, string $destination): bool
{
    if (! is_dir($source) || ! is_dir($destination)) {
        return false;
    }

    try {
        $source = rtrim($source, '/\\');
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $entry) {
            $sourcePath = $entry->getPathname();
            if ($entry->isLink()) {
                return false;
            }

            $relative = substr($sourcePath, strlen($source) + 1);
            $destinationPath = $destination.'/'.$relative;

            if ($entry->isDir()) {
                if (! is_dir($destinationPath) && ! @mkdir($destinationPath, 0775, true)) {
                    return false;
                }
                @chmod($destinationPath, 0775);
                continue;
            }

            $destinationParent = dirname($destinationPath);
            if (! is_dir($destinationParent) && ! @mkdir($destinationParent, 0775, true)) {
                return false;
            }
            if (! @copy($sourcePath, $destinationPath)) {
                return false;
            }
            @chmod($destinationPath, 0664);
        }
    } catch (Throwable) {
        return false;
    }

    return true;
}

function installer_best_effort_remove_tree(string $path): void
{
    if (! is_dir($path)) {
        return;
    }

    installer_make_tree_removable($path);
    installer_remove_tree($path);
}
