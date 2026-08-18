<?php

declare(strict_types=1);

$base = dirname(__DIR__);
$errors = [];
$need = [
    'index.php', 'private-gather.php', '.htaccess', 'install/index.php', 'install/lib.php', 'install/schema.sql',
    'docs/INSTALLATION.md', 'storage/app', 'bootstrap/cache',
];
foreach ($need as $path) {
    if (! file_exists($base.'/'.$path)) {
        $errors[] = 'Missing '.$path;
    }
}

$rootIndex = (string) @file_get_contents($base.'/index.php');
$frontController = (string) @file_get_contents($base.'/private-gather.php');
$rootHt = (string) @file_get_contents($base.'/.htaccess');
$installIndex = (string) @file_get_contents($base.'/install/index.php');
$installLib = (string) @file_get_contents($base.'/install/lib.php');
$schema = (string) @file_get_contents($base.'/install/schema.sql');
$userMigration = (string) @file_get_contents($base.'/database/migrations/0001_01_01_000000_create_users_table.php');

$assertions = [
    ['compatibility index delegates to reliable controller', str_contains($rootIndex, "require __DIR__.'/private-gather.php'")],
    ['pre-install flow is inline and redirect-free', str_contains($frontController, "require \$base.'/install/index.php'") && str_contains($frontController, 'X-Private-Gather-Installer: inline') && ! preg_match('/header\s*\(\s*[\'\"]Location:/i', $frontController)],
    ['installed lock recognition', str_contains($frontController, 'installed.lock')],
    ['APP_INSTALLED recognition', str_contains($frontController, 'APP_INSTALLED')],
    ['subdirectory mount detection', str_contains($frontController, 'private_gather_mount_path') && str_contains($frontController, 'DOCUMENT_ROOT')],
    ['canonical installed script name', str_contains($frontController, "'/index.php'") && str_contains($frontController, "\$_SERVER['SCRIPT_NAME']")],
    ['root application directory protection', str_contains($rootHt, 'app|bootstrap|config|database')],
    ['root public asset forwarding', str_contains($rootHt, 'public/$1')],
    ['root request reaches reliable controller before directory bypass', str_contains($rootHt, 'RewriteRule ^$ private-gather.php')],
    ['explicit index reaches reliable controller', str_contains($rootHt, 'RewriteRule ^index\\.php$ private-gather.php')],
    ['installer schema import', str_contains($installIndex, 'installer_import_schema')],
    ['installer admin creation', str_contains($installIndex, 'installer_create_admin')],
    ['installer environment generation', str_contains($installIndex, 'installer_write_env')],
    ['installer self removal', str_contains($installIndex, 'installer_disable_self')],
    ['installer permanent lock', str_contains($installLib, 'installed.lock')],
    ['installer receipt identity present', str_contains($installLib, "'installer_version'")],
    ['installer avoids false DDL transaction', ! str_contains($installLib, '$pdo->beginTransaction()') && str_contains($installLib, "preg_replace('/^\\s*--.*$/m'")],
    ['schema migration registry', str_contains($schema, 'CREATE TABLE IF NOT EXISTS migrations')],
    ['browser installer registers invite token data hardening', str_contains($installIndex, '2026_08_18_021500_hash_existing_event_invitation_tokens')],
    ['platform admin schema', str_contains($schema, 'is_platform_admin') && str_contains($userMigration, 'is_platform_admin')],
    ['fresh 1.0 security schema', str_contains($schema, 'two_factor_secret') && str_contains($schema, 'consent_records')],
    ['fresh 1.0 commerce schema', str_contains($schema, 'ticket_types') && str_contains($schema, 'orders') && str_contains($schema, 'tickets')],
    ['fresh 1.0 SaaS schema', str_contains($schema, 'tenant_subscriptions') && str_contains($schema, 'tenant_branding')],
    ['fresh schema is retry-safe from DDL ALTER traps', ! preg_match('/^\s*ALTER\s+TABLE\b/im', $schema)],
    ['plan seed is retry-safe', preg_match('/INSERT\s+INTO\s+plans\b[\s\S]*?ON\s+DUPLICATE\s+KEY\s+UPDATE/i', $schema) === 1],
    ['fresh users table includes post-foundation security fields', str_contains($schema, 'two_factor_secret') && str_contains($schema, 'privacy_accepted_at') && str_contains($schema, 'adult_confirmed_at')],
    ['fresh event table includes expanded event fields', str_contains($schema, 'recurrence_rule') && str_contains($schema, 'waitlist_enabled') && str_contains($schema, 'parent_event_id')],
];
foreach ($assertions as [$label, $ok]) {
    if (! $ok) {
        $errors[] = 'Failed assertion: '.$label;
    }
}

$migrations = glob($base.'/database/migrations/*.php') ?: [];
foreach ($migrations as $file) {
    $name = basename($file, '.php');
    // Most fresh-install migrations are represented directly in schema.sql.
    // Data-only migrations that are a no-op on an empty fresh database may be
    // explicitly registered by install/index.php after the schema import.
    if (! str_contains($schema, $name) && ! str_contains($installIndex, $name)) {
        $errors[] = 'Installer does not register migration '.$name;
    }
}

if ($errors) {
    fwrite(STDERR, "INSTALLER VERIFY: FAIL\n- ".implode("\n- ", $errors)."\n");
    exit(1);
}

echo "INSTALLER VERIFY: PASS\n";
echo "Inline first-run installer, subdirectory-safe front controller, retry-safe fresh schema, install lock, migration bookkeeping, and installer self-removal are structurally present.\n";
