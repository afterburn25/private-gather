<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$required = [
    'app/Support/Edition.php' => ['SELF_HOSTED', 'isHosted', 'isSelfHosted'],
    'config/edition.php' => ['PRIVATE_GATHER_EDITION', 'SELF_HOSTED_VISIBILITY', 'SELF_HOSTED_REGISTRATION'],
    'app/Http/Middleware/EnforceSelfHostedPrivacy.php' => ['selfHostedVisibility', 'registrationEnabled'],
    'app/Http/Middleware/ResolveTenantByDomain.php' => ['selfHostedTenant', 'tenant_self_hosted'],
    'install/index.php' => ["'edition' => 'hosted'", 'PRIVATE GATHER · INSTALLER', '<h1>Install Private Gather</h1>'],
    'install/self-hosted-index.php' => ['SELF-HOSTED INSTALLER', "'edition' => 'self_hosted'", 'organization_name'],
    'install/runtime.php' => ['installer_prepare_runtime_directories', 'bootstrap/cache'],
    'install/lib.php' => ['installer_create_self_hosted_tenant', 'SELF_HOSTED_TENANT_ID', 'PRIVATE_GATHER_EDITION'],
    '.env.example' => ['PRIVATE_GATHER_EDITION=hosted', 'SELF_HOSTED_VISIBILITY=private'],
];

$failures = [];
foreach ($required as $path => $needles) {
    $full = $root.'/'.$path;
    if (! is_file($full)) {
        $failures[] = 'Missing '.$path;
        continue;
    }
    $contents = (string) file_get_contents($full);
    foreach ($needles as $needle) {
        if (! str_contains($contents, $needle)) {
            $failures[] = $path.' missing '.$needle;
        }
    }
}

$hostedInstaller = (string) @file_get_contents($root.'/install/index.php');
$selfHostedInstaller = (string) @file_get_contents($root.'/install/self-hosted-index.php');
if (str_contains($hostedInstaller, 'name="edition"') || str_contains($selfHostedInstaller, 'name="edition"')) {
    $failures[] = 'Deployable installer source still exposes an edition selector.';
}
if (str_contains($hostedInstaller, 'name="organization_name"')) {
    $failures[] = 'Hosted installer still exposes Self-Hosted organization setup.';
}
if (! str_contains($selfHostedInstaller, 'name="organization_name"')) {
    $failures[] = 'Self-Hosted installer is missing dedicated organization setup.';
}

$forbiddenHostedPresentation = [
    'HOSTED INSTALLER',
    'Install Private Gather Hosted',
    'Install Hosted Platform',
    'Hosted platform base',
    'Self-Hosted mode in this package',
];
foreach ($forbiddenHostedPresentation as $needle) {
    if (str_contains($hostedInstaller, $needle)) {
        $failures[] = 'Hosted browser installer exposes internal edition wording: '.$needle;
    }
}

$layout = (string) @file_get_contents($root.'/resources/views/layouts/app.blade.php');
if (! str_contains($layout, 'Edition::registrationEnabled()')) {
    $failures[] = 'Shared application layout is not edition-aware.';
}

$adminLayout = (string) @file_get_contents($root.'/resources/views/layouts/admin.blade.php');
if (! str_contains($adminLayout, 'Self-Hosted Edition')) {
    $failures[] = 'Shared admin layout does not identify Self-Hosted Edition when that base is running.';
}

if ($failures !== []) {
    fwrite(STDERR, "Edition verification failed:\n - ".implode("\n - ", $failures)."\n");
    exit(1);
}

echo "Private Gather separate deployment bases with clean Hosted installer presentation and shared maintained Core: PASS\n";
