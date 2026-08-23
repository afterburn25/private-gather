<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$required = [
    'app/Support/Edition.php' => ['SELF_HOSTED', 'isHosted', 'isSelfHosted'],
    'config/edition.php' => ['PRIVATE_GATHER_EDITION', 'SELF_HOSTED_VISIBILITY', 'SELF_HOSTED_REGISTRATION'],
    'app/Http/Middleware/EnforceSelfHostedPrivacy.php' => ['selfHostedVisibility', 'registrationEnabled'],
    'app/Http/Middleware/ResolveTenantByDomain.php' => ['selfHostedTenant', 'tenant_self_hosted'],
    'install/index.php' => ["'edition' => 'hosted'", 'there is no customer edition choice'],
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

$layout = (string) @file_get_contents($root.'/resources/views/layouts/app.blade.php');
if (! str_contains($layout, 'Edition::registrationEnabled()')) {
    $failures[] = 'Public layout is not edition-aware.';
}

$adminLayout = (string) @file_get_contents($root.'/resources/views/layouts/admin.blade.php');
if (! str_contains($adminLayout, 'Self-Hosted Edition')) {
    $failures[] = 'Admin layout does not identify Self-Hosted Edition when that compatibility mode is active.';
}

$installer = (string) @file_get_contents($root.'/install/index.php');
if (str_contains($installer, 'name="edition"') || str_contains($installer, 'Hosted Edition</option>') || str_contains($installer, 'Self-Hosted Edition</option>')) {
    $failures[] = 'Installer reintroduced a customer-facing software edition selector; Private Gather is one business platform.';
}

if ($failures !== []) {
    fwrite(STDERR, "Edition verification failed:\n - ".implode("\n - ", $failures)."\n");
    exit(1);
}

echo "Private Gather unified platform with internal edition compatibility: PASS\n";
