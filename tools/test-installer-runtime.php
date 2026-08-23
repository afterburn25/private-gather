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
    foreach (scandir($path) ?: [] as $item) {
        if ($item === '.' || $item === '..') continue;
        $full = $path.DIRECTORY_SEPARATOR.$item;
        is_dir($full) && ! is_link($full) ? $remove($full) : @unlink($full);
    }
    @chmod($path, 0775);
    @rmdir($path);
};

try {
    mkdir($root.'/storage', 0555, true);
    mkdir($root.'/bootstrap/cache', 0555, true);

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

    echo "INSTALLER RUNTIME REPAIR: PASS\n";
} finally {
    $remove($root);
}
