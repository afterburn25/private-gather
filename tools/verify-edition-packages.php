<?php

declare(strict_types=1);

if (! class_exists(ZipArchive::class)) {
    fwrite(STDERR, "ZipArchive extension is required.\n");
    exit(1);
}

$hosted = (string) ($argv[1] ?? '');
$selfHosted = (string) ($argv[2] ?? '');
$expectedSource = trim((string) ($argv[3] ?? getenv('GITHUB_SHA') ?: ''));

if ($hosted === '' || $selfHosted === '') {
    fwrite(STDERR, "Usage: php tools/verify-edition-packages.php <hosted.zip> <self-hosted.zip> [source-sha]\n");
    exit(2);
}

$failures = [];
$editionSpecific = ['.env.example', 'install/index.php', 'EDITION-PRESET', 'BASE-PRESET', 'PACKAGE-METADATA.json'];
$requiredAssets = [
    'app.css',
    'redesign.css',
    'redesign-compat.css',
    'platform-themes.css',
    'tenant-themes.css',
    'product-completion.css',
    'admin.css',
    'redesign.js',
    'branding/private-gather-logo.png',
    'showcase/platform-hero.svg',
    'showcase/club-night.svg',
    'showcase/event-night.svg',
    'showcase/event-social.svg',
    'showcase/community.svg',
];

$inspect = static function (string $path, string $expectedEdition) use (&$failures, $editionSpecific, $requiredAssets): ?array {
    if (! is_file($path)) {
        $failures[] = 'Missing package: '.$path;
        return null;
    }

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        $failures[] = 'Unreadable ZIP: '.$path;
        return null;
    }

    $names = [];
    $sharedHashes = [];
    $canonicalAssets = [];
    $mirroredAssets = [];

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = (string) $zip->getNameIndex($i);
        $names[] = $name;
        $lower = strtolower($name);
        $lowerBasename = strtolower(basename($name));

        if (
            $lowerBasename === 'auth.json'
            || $lowerBasename === '.env'
            || (str_starts_with($lowerBasename, '.env.') && $lowerBasename !== '.env.example')
            || $lowerBasename === 'installed.lock'
            || $lowerBasename === 'install-record.json'
            || $lower === 'database/database.sqlite'
            || str_ends_with($lower, '/database/database.sqlite')
            || $lower === 'storage/app/showcase-bootstrap.pending'
            || $lower === 'storage/app/showcase-bootstrap.done'
        ) {
            $failures[] = basename($path).' contains protected deployment state: '.$name;
        }

        if (str_starts_with($lower, 'storage/logs/') && ! str_ends_with($lower, '.gitignore')) {
            $failures[] = basename($path).' contains runtime log data: '.$name;
        }

        if ($name === 'install/self-hosted-index.php') {
            $failures[] = basename($path).' leaked the packaging-only Self-Hosted installer template.';
        }

        if (! str_ends_with($name, '/')) {
            $contents = $zip->getFromIndex($i);
            if (! is_string($contents)) {
                $failures[] = basename($path).' cannot read entry '.$name;
            } else {
                $hash = hash('sha256', $contents);
                if (str_starts_with($name, 'public/assets/')) {
                    $canonicalAssets[substr($name, strlen('public/assets/'))] = $hash;
                } elseif (str_starts_with($name, 'assets/')) {
                    $mirroredAssets[substr($name, strlen('assets/'))] = $hash;
                }

                if (! in_array($name, $editionSpecific, true)) {
                    $sharedHashes[$name] = $hash;
                }
            }
        }
    }

    foreach ([
        'index.php', 'private-gather.php', 'artisan', 'composer.json', 'install/index.php', 'install/runtime.php', 'install/cleanup.php', 'install/showcase.php', 'vendor/autoload.php',
        'EDITION-PRESET', 'BASE-PRESET', 'PACKAGE-METADATA.json',
    ] as $required) {
        if (! in_array($required, $names, true)) {
            $failures[] = basename($path).' missing '.$required;
        }
    }

    foreach ($requiredAssets as $asset) {
        if (! isset($canonicalAssets[$asset])) {
            $failures[] = basename($path).' missing canonical public asset public/assets/'.$asset;
        }
        if (! isset($mirroredAssets[$asset])) {
            $failures[] = basename($path).' missing root compatibility asset assets/'.$asset;
        }
    }

    ksort($canonicalAssets);
    ksort($mirroredAssets);
    if ($canonicalAssets === []) {
        $failures[] = basename($path).' contains no canonical public/assets files.';
    } elseif ($canonicalAssets !== $mirroredAssets) {
        $allAssets = array_unique(array_merge(array_keys($canonicalAssets), array_keys($mirroredAssets)));
        sort($allAssets);
        foreach ($allAssets as $asset) {
            if (($canonicalAssets[$asset] ?? null) !== ($mirroredAssets[$asset] ?? null)) {
                $failures[] = basename($path).' static asset mirror differs or is missing: '.$asset;
            }
        }
    }

    $preset = trim((string) $zip->getFromName('EDITION-PRESET'));
    if ($preset !== $expectedEdition) {
        $failures[] = basename($path).' edition preset mismatch: '.$preset;
    }

    $expectedBase = $expectedEdition === 'hosted' ? 'hosted-platform' : 'self-hosted-organization';
    $basePreset = trim((string) $zip->getFromName('BASE-PRESET'));
    if ($basePreset !== $expectedBase) {
        $failures[] = basename($path).' installation base mismatch: '.$basePreset;
    }

    $env = (string) $zip->getFromName('.env.example');
    if (! preg_match('/^PRIVATE_GATHER_EDITION='.preg_quote($expectedEdition, '/').'$/m', $env)) {
        $failures[] = basename($path).' .env.example does not preset '.$expectedEdition.'.';
    }
    if ($expectedEdition === 'hosted') {
        if (preg_match('/^SELF_HOSTED_[A-Z0-9_]+=/m', $env)) {
            $failures[] = basename($path).' Hosted .env.example still exposes Self-Hosted options.';
        }
    } else {
        foreach (['SELF_HOSTED_TENANT_ID=', 'SELF_HOSTED_VISIBILITY=', 'SELF_HOSTED_REGISTRATION='] as $requiredSetting) {
            if (! str_contains($env, $requiredSetting)) {
                $failures[] = basename($path).' Self-Hosted .env.example missing '.$requiredSetting;
            }
        }
        if (! preg_match('/^PLATFORM_WILDCARD_ENABLED=false$/m', $env)) {
            $failures[] = basename($path).' Self-Hosted .env.example must disable platform wildcard mode.';
        }
        if (preg_match('/^PLATFORM_(DOMAIN_TARGET|WILDCARD_TARGET)=/m', $env)) {
            $failures[] = basename($path).' Self-Hosted .env.example still exposes Hosted wildcard target options.';
        }
    }

    $installer = (string) $zip->getFromName('install/index.php');
    if (! str_contains($installer, "'edition' => '".$expectedEdition."'")) {
        $failures[] = basename($path).' dedicated installer does not lock '.$expectedEdition.'.';
    }
    if (str_contains($installer, 'name="edition"') || str_contains($installer, 'Choose Edition')) {
        $failures[] = basename($path).' still exposes an edition selector.';
    }
    if (! str_contains($installer, 'installer_prepare_runtime_directories')) {
        $failures[] = basename($path).' installer does not auto-provision runtime directories.';
    }
    if ($expectedEdition === 'hosted') {
        foreach (['organization_name', 'self_hosted_visibility', 'self_hosted_registration'] as $selfHostedField) {
            if (str_contains($installer, 'name="'.$selfHostedField.'"')) {
                $failures[] = basename($path).' Hosted installer leaks Self-Hosted field '.$selfHostedField.'.';
            }
        }
        if (! str_contains($installer, 'installer_seed_hosted_showcase($installInput)')) {
            $failures[] = basename($path).' Hosted installer does not bootstrap the fictional showcase dataset.';
        }
    } else {
        foreach (['organization_name', 'self_hosted_visibility', 'self_hosted_registration'] as $requiredField) {
            if (! str_contains($installer, 'name="'.$requiredField.'"')) {
                $failures[] = basename($path).' Self-Hosted installer missing '.$requiredField.'.';
            }
        }
        if (str_contains($installer, 'installer_seed_hosted_showcase($installInput)')) {
            $failures[] = basename($path).' Self-Hosted installer must not seed the Hosted showcase dataset.';
        }
    }

    $metadata = json_decode((string) $zip->getFromName('PACKAGE-METADATA.json'), true);
    if (! is_array($metadata)) {
        $failures[] = basename($path).' has invalid package metadata.';
        $metadata = null;
    } else {
        if (($metadata['schema'] ?? null) !== 2) {
            $failures[] = basename($path).' metadata schema mismatch.';
        }
        if (($metadata['product'] ?? null) !== 'privategather/private-gather') {
            $failures[] = basename($path).' metadata product mismatch.';
        }
        if (($metadata['edition'] ?? null) !== $expectedEdition) {
            $failures[] = basename($path).' metadata edition mismatch.';
        }
        if (($metadata['installation_base'] ?? null) !== $expectedBase) {
            $failures[] = basename($path).' metadata installation base mismatch.';
        }
        if (($metadata['dedicated_installer'] ?? null) !== true) {
            $failures[] = basename($path).' metadata does not assert a dedicated installer.';
        }
        if (($metadata['shared_core'] ?? null) !== true) {
            $failures[] = basename($path).' metadata does not assert shared Core.';
        }
        if (($metadata['static_asset_mirror'] ?? null) !== true) {
            $failures[] = basename($path).' metadata does not assert the static asset compatibility mirror.';
        }
        if ((int) ($metadata['static_asset_files'] ?? 0) !== count($canonicalAssets)) {
            $failures[] = basename($path).' metadata static asset count does not match package contents.';
        }
    }

    $zip->close();
    ksort($sharedHashes);

    return [
        'metadata' => $metadata,
        'shared_hashes' => $sharedHashes,
        'canonical_assets' => $canonicalAssets,
        'mirrored_assets' => $mirroredAssets,
    ];
};

$hostedResult = $inspect($hosted, 'hosted');
$selfResult = $inspect($selfHosted, 'self_hosted');

if (is_array($hostedResult) && is_array($selfResult)) {
    $hostedMeta = $hostedResult['metadata'];
    $selfMeta = $selfResult['metadata'];

    if (is_array($hostedMeta) && is_array($selfMeta)) {
        if (($hostedMeta['source_commit'] ?? null) !== ($selfMeta['source_commit'] ?? null)) {
            $failures[] = 'Hosted and Self-Hosted packages were not built from the same source commit.';
        }
        if (($hostedMeta['version'] ?? null) !== ($selfMeta['version'] ?? null)) {
            $failures[] = 'Hosted and Self-Hosted package versions differ.';
        }
        if ($expectedSource !== '' && ($hostedMeta['source_commit'] ?? null) !== $expectedSource) {
            $failures[] = 'Package source commit does not match expected source '.$expectedSource.'.';
        }
    }

    if ($hostedResult['shared_hashes'] !== $selfResult['shared_hashes']) {
        $allNames = array_unique(array_merge(array_keys($hostedResult['shared_hashes']), array_keys($selfResult['shared_hashes'])));
        sort($allNames);
        foreach ($allNames as $name) {
            if (($hostedResult['shared_hashes'][$name] ?? null) !== ($selfResult['shared_hashes'][$name] ?? null)) {
                $failures[] = 'Shared Core entry differs between editions: '.$name;
            }
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, "EDITION PACKAGE VERIFY: FAIL\n - ".implode("\n - ", $failures)."\n");
    exit(1);
}

echo "EDITION PACKAGE VERIFY: PASS\n";
echo "STATIC ASSET DELIVERY VERIFY: PASS\n";
echo "SHOWCASE PACKAGE VERIFY: PASS\n";
