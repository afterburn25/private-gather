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

$inspect = static function (string $path, string $expectedEdition) use (&$failures, $editionSpecific): ?array {
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
        ) {
            $failures[] = basename($path).' contains protected deployment state: '.$name;
        }

        if (str_starts_with($lower, 'storage/logs/') && ! str_ends_with($lower, '.gitignore')) {
            $failures[] = basename($path).' contains runtime log data: '.$name;
        }

        if ($name === 'install/self-hosted-index.php') {
            $failures[] = basename($path).' leaked the packaging-only Self-Hosted installer template.';
        }

        if (! in_array($name, $editionSpecific, true) && ! str_ends_with($name, '/')) {
            $contents = $zip->getFromIndex($i);
            if (! is_string($contents)) {
                $failures[] = basename($path).' cannot read shared entry '.$name;
            } else {
                $sharedHashes[$name] = hash('sha256', $contents);
            }
        }
    }

    foreach ([
        'index.php', 'private-gather.php', 'artisan', 'composer.json', 'install/index.php', 'install/runtime.php', 'install/cleanup.php', 'vendor/autoload.php',
        'EDITION-PRESET', 'BASE-PRESET', 'PACKAGE-METADATA.json',
    ] as $required) {
        if (! in_array($required, $names, true)) {
            $failures[] = basename($path).' missing '.$required;
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
    } else {
        foreach (['organization_name', 'self_hosted_visibility', 'self_hosted_registration'] as $requiredField) {
            if (! str_contains($installer, 'name="'.$requiredField.'"')) {
                $failures[] = basename($path).' Self-Hosted installer missing '.$requiredField.'.';
            }
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
    }

    $zip->close();
    ksort($sharedHashes);

    return ['metadata' => $metadata, 'shared_hashes' => $sharedHashes];
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
