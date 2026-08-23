<?php

declare(strict_types=1);

require dirname(__DIR__).'/install/lib.php';
require dirname(__DIR__).'/install/runtime.php';
require dirname(__DIR__).'/install/database.php';

$installerSource = (string) file_get_contents(dirname(__DIR__).'/install/index.php');
$forbiddenVisibleLabels = [
    'Install Hosted Platform',
    'This installer always provisions the Hosted platform base',
    'There is no edition selector and no Self-Hosted mode in this package',
    'Install Private Gather Hosted',
    'PRIVATE GATHER · HOSTED INSTALLER',
    'Private Gather Hosted Installer',
    'Private Gather Hosted is configured and ready',
];
foreach ($forbiddenVisibleLabels as $label) {
    if (str_contains($installerSource, $label)) {
        throw new RuntimeException('Hosted browser installer still exposes edition jargon: '.$label);
    }
}
if (! str_contains($installerSource, '<h2>5. Install Private Gather</h2>') || ! str_contains($installerSource, '>Install Private Gather</button>')) {
    throw new RuntimeException('Hosted browser installer does not expose the clean Private Gather install action.');
}

$autoDsn = installer_database_dsn('localhost', null, 'private_gather');
if ($autoDsn !== 'mysql:host=localhost;dbname=private_gather;charset=utf8mb4' || str_contains($autoDsn, 'port=3306')) {
    throw new RuntimeException('localhost Auto mode is forcing an unexpected MySQL port: '.$autoDsn);
}
if (installer_database_port(['db_port' => '']) !== null) {
    throw new RuntimeException('Blank database port must resolve to Auto/null.');
}
if (installer_database_port(['db_port' => '3307']) !== 3307) {
    throw new RuntimeException('Explicit MySQL port was not preserved.');
}
if (! str_contains($installerSource, "'db_port' => ''") || str_contains($installerSource, "'db_port' => '3306'")) {
    throw new RuntimeException('Installer database port default is not Auto/blank.');
}
if (! str_contains($installerSource, 'installer_connect_database_auto') || ! str_contains($installerSource, 'installer_finalize_database_env')) {
    throw new RuntimeException('Installer is not using automatic MySQL transport end-to-end.');
}

$databaseConfig = (string) file_get_contents(dirname(__DIR__).'/config/database.php');
if (! str_contains($databaseConfig, "'port' => \$mysqlPort === '' ? null : \$mysqlPort")) {
    throw new RuntimeException('Laravel database config does not preserve blank/automatic port mode.');
}

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
    mkdir($root.'/storage/app', 0755, true);
    file_put_contents($root.'/storage/app/preserve.txt', 'storage-preserved');
    @chmod($root.'/storage/app/preserve.txt', 0444);
    @chmod($root.'/storage/app', 0555);
    @chmod($root.'/storage', 0555);

    if (! installer_atomic_rebuild_tree($root, 'storage')) {
        throw new RuntimeException('Ownership-neutral storage rebuild failed.');
    }
    if (file_get_contents($root.'/storage/app/preserve.txt') !== 'storage-preserved') {
        throw new RuntimeException('Storage rebuild did not preserve existing files.');
    }
    if (! installer_runtime_write_probe($root.'/storage')) {
        throw new RuntimeException('Storage is not writable after ownership-neutral rebuild.');
    }

    mkdir($root.'/bootstrap/cache', 0755, true);
    file_put_contents($root.'/bootstrap/providers.php', '<?php return [];');
    file_put_contents($root.'/bootstrap/cache/.gitignore', "*\n!.gitignore\n");
    @chmod($root.'/bootstrap/providers.php', 0444);
    @chmod($root.'/bootstrap/cache/.gitignore', 0444);
    @chmod($root.'/bootstrap/cache', 0555);
    @chmod($root.'/bootstrap', 0555);

    if (! installer_atomic_rebuild_tree($root, 'bootstrap')) {
        throw new RuntimeException('Ownership-neutral bootstrap rebuild failed.');
    }
    if (file_get_contents($root.'/bootstrap/providers.php') !== '<?php return [];') {
        throw new RuntimeException('Bootstrap rebuild did not preserve application bootstrap files.');
    }
    if (! installer_runtime_write_probe($root.'/bootstrap/cache')) {
        throw new RuntimeException('bootstrap/cache is not writable after ownership-neutral rebuild.');
    }

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

    foreach (glob($root.'/.storage-pg-*') ?: [] as $leftover) {
        if (file_exists($leftover)) {
            throw new RuntimeException('Storage repair left a staging/backup directory behind: '.$leftover);
        }
    }
    foreach (glob($root.'/.bootstrap-pg-*') ?: [] as $leftover) {
        if (file_exists($leftover)) {
            throw new RuntimeException('Bootstrap repair left a staging/backup directory behind: '.$leftover);
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

    echo "INSTALLER PRESENTATION CLEANUP: PASS\n";
    echo "INSTALLER DATABASE AUTO-TRANSPORT: PASS\n";
    echo "INSTALLER OWNERSHIP-NEUTRAL REBUILD: PASS\n";
    echo "INSTALLER RUNTIME REPAIR: PASS\n";
    echo "INSTALLER AUTO-DELETE: PASS\n";
} finally {
    $remove($root);
}
