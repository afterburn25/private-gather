<?php

declare(strict_types=1);

require dirname(__DIR__).'/install/lib.php';
require dirname(__DIR__).'/install/runtime.php';

$root = sys_get_temp_dir().'/private-gather-installer-runtime-'.bin2hex(random_bytes(5));
if (! mkdir($root, 0755, true) && ! is_dir($root)) {
    throw new RuntimeException('Unable to create temporary installer test root.');
}

$remove = static function (string $path) use (&$remove): void {
    if (! is_dir($path)) {
        @unlink($path);
        return;
    }
    @chmod($path, 0775);
    foreach (scandir($path) ?: [] as $item) {
        if ($item === '.' || $item === '..') continue;
        $full = $path.DIRECTORY_SEPARATOR.$item;
        is_dir($full) && ! is_link($full) ? $remove($full) : @unlink($full);
    }
    @rmdir($path);
};

try {
    mkdir($root.'/storage', 0555, true);
    mkdir($root.'/bootstrap', 0755, true);
    mkdir($root.'/bootstrap/cache', 0755, false);
    @chmod($root.'/bootstrap/cache', 0555);

    $results = installer_prepare_runtime_directories($root);
    $failures = array_keys(array_filter($results, static fn (bool $ok): bool => ! $ok));
    if ($failures !== []) {
        throw new RuntimeException('Runtime repair failed for: '.implode(', ', $failures));
    }

    foreach (array_keys($results) as $relative) {
        $path = $root.'/'.$relative;
        if (! is_dir($path) || ! is_writable($path)) {
            throw new RuntimeException('Runtime directory is not writable after repair: '.$relative);
        }
        $mode = fileperms($path) & 0777;
        if (($mode & 0020) === 0) {
            throw new RuntimeException('Runtime directory is not group writable after repair: '.$relative.' mode '.decoct($mode));
        }
    }

    $install = $root.'/install';
    $fallback = $root.'/.install-disabled-test';
    mkdir($install.'/nested', 0755, true);
    mkdir($fallback.'/nested', 0755, true);
    file_put_contents($install.'/index.php', '<?php echo "installer";');
    file_put_contents($install.'/nested/schema.sql', 'schema');
    file_put_contents($fallback.'/index.php', '<?php echo "disabled";');
    file_put_contents($fallback.'/nested/data.txt', 'data');
    @chmod($install.'/index.php', 0444);
    @chmod($install.'/nested/schema.sql', 0444);
    @chmod($fallback.'/index.php', 0444);
    @chmod($fallback.'/nested/data.txt', 0444);
    @chmod($install.'/nested', 0555);
    @chmod($install, 0555);
    @chmod($fallback.'/nested', 0555);
    @chmod($fallback, 0555);

    if (! installer_cleanup_after_success($install)) {
        throw new RuntimeException('Automatic installer deletion reported failure.');
    }
    if (file_exists($install) || file_exists($fallback)) {
        throw new RuntimeException('Installer or disabled fallback remains after automatic cleanup.');
    }

    echo "INSTALLER RUNTIME REPAIR: PASS\n";
    echo "INSTALLER AUTO-DELETE: PASS\n";
} finally {
    $remove($root);
}
