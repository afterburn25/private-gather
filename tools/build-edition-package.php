<?php

declare(strict_types=1);

if (! class_exists(ZipArchive::class)) {
    fwrite(STDERR, "ZipArchive extension is required.\n");
    exit(1);
}

$root = dirname(__DIR__);
$edition = strtolower(trim((string) ($argv[1] ?? '')));
if (! in_array($edition, ['hosted', 'self_hosted'], true)) {
    fwrite(STDERR, "Usage: php tools/build-edition-package.php hosted|self_hosted [source-sha] [output-dir]\n");
    exit(2);
}

$version = trim((string) @file_get_contents($root.'/VERSION'));
if ($version === '') {
    throw new RuntimeException('VERSION is missing or empty.');
}

$sourceSha = trim((string) ($argv[2] ?? getenv('GITHUB_SHA') ?: ''));
if ($sourceSha === '') {
    $sourceSha = trim((string) shell_exec('git -C '.escapeshellarg($root).' rev-parse HEAD 2>/dev/null'));
}
if ($sourceSha === '') {
    $sourceSha = 'unknown';
}

$outputDir = (string) ($argv[3] ?? $root.'/dist');
if (! is_dir($outputDir) && ! mkdir($outputDir, 0775, true) && ! is_dir($outputDir)) {
    throw new RuntimeException('Unable to create output directory: '.$outputDir);
}

$label = $edition === 'hosted' ? 'Hosted' : 'Self-Hosted';
$filename = 'Private-Gather-'.$label.'-'.$version.'.zip';
$output = rtrim($outputDir, '/\\').DIRECTORY_SEPARATOR.$filename;
@unlink($output);

$includeRoots = [
    '.htaccess', '.env.example', 'README.md', 'VERSION', 'artisan', 'composer.json', 'composer.lock',
    'index.php', 'private-gather.php', 'app', 'bootstrap', 'config', 'database', 'docs', 'install', 'public',
    'resources', 'routes', 'storage', 'vendor',
];

$excludedBasenames = ['installed.lock', 'install-record.json', '.DS_Store'];
$runtimePrefixes = [
    'storage/app/public/',
    'storage/app/private/',
    'storage/framework/cache/data/',
    'storage/framework/sessions/',
    'storage/framework/views/',
    'storage/logs/',
];

$zip = new ZipArchive();
if ($zip->open($output, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    throw new RuntimeException('Unable to create '.$output);
}

$added = 0;
$addFile = static function (string $absolute, string $relative) use (
    $zip,
    &$added,
    $excludedBasenames,
    $runtimePrefixes,
    $edition
): void {
    $relative = str_replace('\\', '/', ltrim($relative, '/'));
    $basename = basename($relative);
    $lowerBasename = strtolower($basename);
    if (
        $relative === ''
        || in_array($basename, $excludedBasenames, true)
        || $lowerBasename === 'auth.json'
        || $lowerBasename === '.env'
        || (str_starts_with($lowerBasename, '.env.') && $lowerBasename !== '.env.example')
    ) {
        return;
    }

    if ($relative === 'database/database.sqlite') {
        return;
    }

    foreach ($runtimePrefixes as $prefix) {
        if (str_starts_with($relative, $prefix) && basename($relative) !== '.gitignore') {
            return;
        }
    }

    if (! is_file($absolute) || is_link($absolute)) {
        return;
    }

    if ($relative === 'install/index.php') {
        $contents = (string) file_get_contents($absolute);
        $search = "'edition' => 'hosted',";
        $replacement = "'edition' => '".$edition."',";
        if (! str_contains($contents, $search)) {
            throw new RuntimeException('Installer edition preset marker changed; package build refused.');
        }
        $contents = str_replace($search, $replacement, $contents, $count);
        if ($count !== 1) {
            throw new RuntimeException('Installer edition preset marker must occur exactly once.');
        }
        $zip->addFromString($relative, $contents);
    } elseif ($relative === '.env.example') {
        $contents = (string) file_get_contents($absolute);
        $contents = preg_replace(
            '/^PRIVATE_GATHER_EDITION=.*$/m',
            'PRIVATE_GATHER_EDITION='.$edition,
            $contents,
            1,
            $count
        ) ?? $contents;
        if ($count !== 1) {
            throw new RuntimeException('.env.example edition preset marker must occur exactly once.');
        }
        $zip->addFromString($relative, $contents);
    } else {
        $zip->addFile($absolute, $relative);
    }

    $added++;
};

foreach ($includeRoots as $entry) {
    $absolute = $root.'/'.$entry;
    if (is_file($absolute)) {
        $addFile($absolute, $entry);
        continue;
    }

    if (! is_dir($absolute)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($absolute, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->isLink()) {
            continue;
        }
        $path = $file->getPathname();
        $addFile($path, substr($path, strlen($root) + 1));
    }
}

$metadata = [
    'schema' => 1,
    'product' => 'privategather/private-gather',
    'version' => $version,
    'edition' => $edition,
    'source_commit' => $sourceSha,
    'shared_core' => true,
];

$zip->addFromString('EDITION-PRESET', $edition."\n");
$zip->addFromString(
    'PACKAGE-METADATA.json',
    json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n"
);
$zip->close();

$sha256 = hash_file('sha256', $output);
if (! is_string($sha256)) {
    throw new RuntimeException('Unable to hash package.');
}
file_put_contents($output.'.sha256', $sha256.'  '.$filename."\n");

echo json_encode([
    'file' => $output,
    'edition' => $edition,
    'version' => $version,
    'source_commit' => $sourceSha,
    'files' => $added + 2,
    'sha256' => $sha256,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
