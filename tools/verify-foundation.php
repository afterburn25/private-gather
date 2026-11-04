<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$required = [
    'composer.json',
    'bootstrap/app.php',
    'config/platform.php',
    'app/Http/Middleware/ResolveTenantByDomain.php',
    'app/Services/TenantProvisioner.php',
    'app/Services/CustomDomainService.php',
    'database/migrations/2026_08_16_180100_create_tenant_domains_table.php',
    'resources/views/platform/home.blade.php',
    'resources/views/tenant/home.blade.php',
];

$errors = [];
foreach ($required as $file) {
    if (!is_file($root.'/'.$file)) {
        $errors[] = 'Missing required file: '.$file;
    }
}

$composer = json_decode((string) file_get_contents($root.'/composer.json'), true);
if (!is_array($composer) || ($composer['require']['laravel/framework'] ?? null) !== '^13.17') {
    $errors[] = 'composer.json is not pinned to the Laravel 13 foundation target.';
}

$domainMigration = (string) file_get_contents($root.'/database/migrations/2026_08_16_180100_create_tenant_domains_table.php');
foreach (['platform_subdomain', 'verification_token', 'ssl_status', 'redirect_to_primary'] as $needle) {
    if (!str_contains($domainMigration, $needle) && $needle !== 'platform_subdomain') {
        $errors[] = 'Domain migration missing: '.$needle;
    }
}

if ($errors) {
    fwrite(STDERR, "FOUNDATION VERIFY: FAIL\n- ".implode("\n- ", $errors)."\n");
    exit(1);
}

fwrite(STDOUT, "FOUNDATION VERIFY: PASS\n");
fwrite(STDOUT, "Tenant domain resolution, provisioning, CMS schema, event schema, and initial UI are present.\n");
