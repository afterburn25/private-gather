<?php

declare(strict_types=1);

$base = dirname(__DIR__);
$errors = [];
$need = [
    'index.php',
    'private-gather.php',
    '.htaccess',
    'install/index.php',
    'install/lib.php',
    'install/schema.sql',
    'install/community-schema.php',
    'install/competitive-schema.php',
    'install/hosted-member-network-schema.php',
    'install/hosted-discovery-revenue-schema.php',
    'install/lifestyle-community-suite-schema.php',
    'docs/INSTALLATION.md',
    'storage/app',
    'bootstrap/cache',
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
$communitySchema = (string) @file_get_contents($base.'/install/community-schema.php');
$competitiveSchema = (string) @file_get_contents($base.'/install/competitive-schema.php');
$hostedNetworkSchema = (string) @file_get_contents($base.'/install/hosted-member-network-schema.php');
$hostedDiscoverySchema = (string) @file_get_contents($base.'/install/hosted-discovery-revenue-schema.php');
$lifestyleSchema = (string) @file_get_contents($base.'/install/lifestyle-community-suite-schema.php');
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
    ['installer private community import', str_contains($installIndex, "require_once __DIR__.'/community-schema.php'") && str_contains($installIndex, 'installer_import_private_community')],
    ['installer competitive schema import', str_contains($installIndex, "require_once __DIR__.'/competitive-schema.php'") && str_contains($installIndex, 'installer_import_competitive_schema')],
    ['installer hosted member-network import', str_contains($installIndex, "require_once __DIR__.'/hosted-member-network-schema.php'") && str_contains($installIndex, 'installer_import_hosted_member_network')],
    ['installer hosted discovery import', str_contains($installIndex, "require_once __DIR__.'/hosted-discovery-revenue-schema.php'") && str_contains($installIndex, 'installer_import_hosted_discovery_revenue')],
    ['installer 1.2.3 lifestyle schema import', str_contains($installIndex, "require_once __DIR__.'/lifestyle-community-suite-schema.php'") && str_contains($installIndex, 'installer_import_lifestyle_community_suite')],
    ['competitive installer helper is callable', str_contains($competitiveSchema, 'function installer_import_competitive_schema') && str_contains($competitiveSchema, 'function installer_schema_has_column') && str_contains($competitiveSchema, 'function installer_schema_has_constraint')],
    ['hosted member-network installer helper is callable', str_contains($hostedNetworkSchema, 'function installer_import_hosted_member_network') && str_contains($hostedNetworkSchema, 'community_groups') && str_contains($hostedNetworkSchema, 'private_albums')],
    ['hosted discovery installer helper is callable', str_contains($hostedDiscoverySchema, 'function installer_import_hosted_discovery_revenue') && str_contains($hostedDiscoverySchema, 'club_directory_profiles')],
    ['1.2.3 fresh profile schema parity', str_contains($lifestyleSchema, 'lifestyle_identity') && str_contains($lifestyleSchema, 'message_permissions') && str_contains($lifestyleSchema, 'show_last_active')],
    ['1.2.3 fresh partner schema parity', str_contains($lifestyleSchema, 'profile_partner_invites') && str_contains($lifestyleSchema, 'community_poll_votes') && str_contains($lifestyleSchema, 'club_rewards')],
    ['1.2.3 fresh identity schema parity', str_contains($lifestyleSchema, 'users_username_unique') && str_contains($lifestyleSchema, 'verification_status') && str_contains($lifestyleSchema, 'verification_webhook_events') && str_contains($lifestyleSchema, 'provider_reference_hash')],
    ['1.2.3 schema delta is retry guarded', str_contains($lifestyleSchema, 'installer_add_column_if_missing') && str_contains($lifestyleSchema, 'installer_add_index_if_missing') && str_contains($lifestyleSchema, 'CREATE TABLE IF NOT EXISTS')],
    ['installer admin creation', str_contains($installIndex, 'installer_create_admin')],
    ['installer environment generation', str_contains($installIndex, 'installer_write_env')],
    ['installer self removal', str_contains($installIndex, 'installer_disable_self')],
    ['installer permanent lock', str_contains($installLib, 'installed.lock')],
    ['installer receipt identity present', str_contains($installLib, "'installer_version'")],
    ['installer avoids false DDL transaction', ! str_contains($installLib, '$pdo->beginTransaction()') && str_contains($installLib, "preg_replace('/^\\s*--.*$/m'")],
    ['schema migration registry', str_contains($schema, 'CREATE TABLE IF NOT EXISTS migrations')],
    ['browser installer registers invite token data hardening', str_contains($installIndex, '2026_08_18_021500_hash_existing_event_invitation_tokens')],
    ['browser installer registers 1.2.3 lifestyle schema', str_contains($installIndex, '2026_08_22_030000_expand_lifestyle_community_suite')],
    ['browser installer registers 1.2.3 poll schema', str_contains($installIndex, '2026_08_22_031000_create_community_poll_votes')],
    ['browser installer registers 1.2.3 partner schema', str_contains($installIndex, '2026_08_22_032000_create_profile_partner_invites')],
    ['browser installer registers 1.2.3 rewards schema', str_contains($installIndex, '2026_08_22_033000_create_club_rewards')],
    ['browser installer registers 1.2.3 identity schema', str_contains($installIndex, '2026_08_22_200000_global_username_and_age_verification_v123')],
    ['platform admin schema', str_contains($schema, 'is_platform_admin') && str_contains($userMigration, 'is_platform_admin')],
    ['fresh 1.0 security schema', str_contains($schema, 'two_factor_secret') && str_contains($schema, 'consent_records')],
    ['fresh 1.0 commerce schema', str_contains($schema, 'ticket_types') && str_contains($schema, 'orders') && str_contains($schema, 'tickets')],
    ['fresh 1.0 SaaS schema', str_contains($schema, 'tenant_subscriptions') && str_contains($schema, 'tenant_branding')],
    ['fresh schema is retry-safe from DDL ALTER traps', ! preg_match('/^\s*ALTER\s+TABLE\b/im', $schema)],
    ['plan seed is retry-safe', preg_match('/INSERT\s+INTO\s+plans\b[\s\S]*?ON\s+DUPLICATE\s+KEY\s+UPDATE/i', $schema) === 1],
    ['fresh users table includes post-foundation security fields', str_contains($schema, 'two_factor_secret') && str_contains($schema, 'privacy_accepted_at') && str_contains($schema, 'adult_confirmed_at')],
    ['fresh event table includes expanded event fields', str_contains($schema, 'recurrence_rule') && str_contains($schema, 'waitlist_enabled') && str_contains($schema, 'parent_event_id')],
    ['private community installer helper is callable', str_contains($communitySchema, 'function installer_import_private_community')],
];
foreach ($assertions as [$label, $ok]) {
    if (! $ok) {
        $errors[] = 'Failed assertion: '.$label;
    }
}

$migrations = glob($base.'/database/migrations/*.php') ?: [];
foreach ($migrations as $file) {
    $name = basename($file, '.php');
    // Fresh-install migrations are represented either directly in schema.sql,
    // by one of the installer schema modules, or explicitly registered only
    // after the corresponding schema import completes successfully.
    if (! str_contains($schema, $name) && ! str_contains($installIndex, $name)) {
        $errors[] = 'Installer does not register migration '.$name;
    }
}

if ($errors) {
    fwrite(STDERR, "INSTALLER VERIFY: FAIL\n- ".implode("\n- ", $errors)."\n");
    exit(1);
}

echo "INSTALLER VERIFY: PASS\n";
echo "Inline first-run installer, subdirectory-safe routing, complete schema modules, retry-safe 1.2.3 fresh schema, migration bookkeeping, install lock, and installer self-removal are structurally present.\n";
