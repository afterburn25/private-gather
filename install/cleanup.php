<?php

declare(strict_types=1);

/**
 * Remove installer code after a successful installation.
 *
 * The permanent install lock is written before this function is called. We
 * first use the existing fail-closed remover in production, then make a second
 * cleanup pass over the original /install path and any disabled fallback
 * directories. The optional path exists for regression testing.
 *
 * @return bool true only when no installer directory remains
 */
function installer_cleanup_after_success(?string $installDir = null): bool
{
    $productionPath = $installDir === null;
    $installDir ??= __DIR__;
    $parent = dirname($installDir);

    if ($productionPath) {
        installer_disable_self();
    }

    if (is_dir($installDir)) {
        installer_make_tree_removable($installDir);
        installer_remove_tree($installDir);
    }

    foreach (glob($parent.'/.install-disabled-*', GLOB_ONLYDIR) ?: [] as $disabledDir) {
        installer_make_tree_removable($disabledDir);
        installer_remove_tree($disabledDir);
    }

    if (is_dir($installDir)) {
        return false;
    }

    foreach (glob($parent.'/.install-disabled-*', GLOB_ONLYDIR) ?: [] as $disabledDir) {
        if (is_dir($disabledDir)) {
            return false;
        }
    }

    return true;
}

function installer_make_tree_removable(string $path): void
{
    if (! is_dir($path) || is_link($path)) {
        return;
    }

    @chmod($path, 0775);
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $entry) {
        $entryPath = $entry->getPathname();
        if ($entry->isLink()) {
            @unlink($entryPath);
            continue;
        }
        @chmod($entryPath, $entry->isDir() ? 0775 : 0664);
    }
}

if (! defined('PRIVATE_GATHER_INSTALLER_CLEANUP_REGISTERED')) {
    define('PRIVATE_GATHER_INSTALLER_CLEANUP_REGISTERED', true);
    register_shutdown_function(static function (): void {
        if (function_exists('installer_is_installed') && installer_is_installed()) {
            installer_cleanup_after_success();
        }
    });
}
