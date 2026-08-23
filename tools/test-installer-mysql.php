<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/install/lib.php';
require_once dirname(__DIR__).'/install/community-schema.php';
require_once dirname(__DIR__).'/install/competitive-schema.php';
require_once dirname(__DIR__).'/install/hosted-member-network-schema.php';
require_once dirname(__DIR__).'/install/hosted-discovery-revenue-schema.php';
require_once dirname(__DIR__).'/install/lifestyle-community-suite-schema.php';

$host = getenv('PG_MYSQL_HOST') ?: '127.0.0.1';
$port = (int) (getenv('PG_MYSQL_PORT') ?: '3306');
$database = getenv('PG_MYSQL_DATABASE') ?: 'private_gather_installer';
$username = getenv('PG_MYSQL_USERNAME') ?: 'root';
$password = getenv('PG_MYSQL_PASSWORD') ?: 'root';

if (! preg_match('/^[A-Za-z0-9_]+$/', $database)) {
    throw new RuntimeException('Unsafe MySQL smoke-test database name.');
}

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

$server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, $options);
$server->exec('DROP DATABASE IF EXISTS `'.$database.'`');
$server->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$pdo = new PDO("mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4", $username, $password, $options);

$_POST['edition'] = 'hosted';

installer_import_schema($pdo);
installer_import_private_community($pdo);
installer_import_competitive_schema($pdo);
installer_import_hosted_member_network($pdo);
installer_import_hosted_discovery_revenue($pdo);
installer_import_lifestyle_community_suite($pdo);

// The August 22 parity layer is deliberately retry-safe. A second application
// must be a no-op rather than a duplicate-column/index/constraint failure.
installer_import_lifestyle_community_suite($pdo);

$requiredTables = [
    'users',
    'profiles',
    'community_posts',
    'community_poll_votes',
    'profile_partner_invites',
    'club_rewards',
    'member_point_ledger',
    'reward_redemptions',
    'verification_webhook_events',
    'community_groups',
    'private_albums',
    'club_directory_profiles',
];

$tableExists = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
foreach ($requiredTables as $table) {
    $tableExists->execute([$table]);
    if ((int) $tableExists->fetchColumn() !== 1) {
        throw new RuntimeException('Fresh installer did not create required table: '.$table);
    }
}

$requiredColumns = [
    ['profiles', 'lifestyle_identity'],
    ['profiles', 'message_permissions'],
    ['profiles', 'show_last_active'],
    ['community_posts', 'wall_user_id'],
    ['community_posts', 'group_id'],
    ['messages', 'attachment_path'],
    ['notification_preferences', 'in_app_connections'],
    ['platform_notifications', 'tenant_id'],
    ['users', 'username'],
    ['users', 'verification_status'],
    ['users', 'verification_level'],
    ['users', 'identity_verified_at'],
    ['users', 'identity_verification_expires_at'],
    ['verifications', 'provider_reference_hash'],
];

$columnExists = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
foreach ($requiredColumns as [$table, $column]) {
    $columnExists->execute([$table, $column]);
    if ((int) $columnExists->fetchColumn() !== 1) {
        throw new RuntimeException("Fresh installer is missing required column {$table}.{$column}");
    }
}

$index = $pdo->prepare('SELECT NON_UNIQUE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=? LIMIT 1');
$index->execute(['users', 'users_username_unique']);
$nonUnique = $index->fetchColumn();
if ($nonUnique === false || (int) $nonUnique !== 0) {
    throw new RuntimeException('Fresh installer did not create the global unique username index.');
}

$registeredMigrations = [
    '2026_08_18_021500_hash_existing_event_invitation_tokens',
    '2026_08_18_210000_create_private_community',
    '2026_08_19_210000_create_membership_addons_promoters_marketing',
    '2026_08_19_220000_create_hosted_member_network',
    '2026_08_19_230000_create_hosted_member_matching',
    '2026_08_19_230000_create_hosted_club_directory_and_affiliate_revenue',
    '2026_08_22_030000_expand_lifestyle_community_suite',
    '2026_08_22_031000_create_community_poll_votes',
    '2026_08_22_032000_create_profile_partner_invites',
    '2026_08_22_033000_create_club_rewards',
    '2026_08_22_200000_global_username_and_age_verification_v123',
];
$stmt = $pdo->prepare('INSERT INTO migrations (migration,batch) SELECT ?,1 WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration=?)');
foreach ($registeredMigrations as $migration) {
    $stmt->execute([$migration, $migration]);
}

$adminId = installer_create_admin($pdo, [
    'admin_email' => 'platform-admin@example.test',
    'admin_name' => 'Platform Administrator',
    'admin_password' => 'InstallerSmokePassword123',
]);
$admin = $pdo->prepare('SELECT username,is_platform_admin FROM users WHERE id=?');
$admin->execute([$adminId]);
$adminRow = $admin->fetch();
if (! is_array($adminRow) || trim((string) ($adminRow['username'] ?? '')) === '' || (int) ($adminRow['is_platform_admin'] ?? 0) !== 1) {
    throw new RuntimeException('Fresh installer administrator does not satisfy the 1.2.3 global-username/admin contract.');
}

$pending = [];
foreach (glob(dirname(__DIR__).'/database/migrations/*.php') ?: [] as $file) {
    $migration = basename($file, '.php');
    $check = $pdo->prepare('SELECT COUNT(*) FROM migrations WHERE migration=?');
    $check->execute([$migration]);
    if ((int) $check->fetchColumn() !== 1) {
        // schema.sql contains the normal historical migration registry. The
        // installer-specific registrations above cover schema-module deltas.
        $pending[] = $migration;
    }
}
if ($pending !== []) {
    throw new RuntimeException('Fresh installer leaves migrations pending: '.implode(', ', $pending));
}

echo "MYSQL INSTALLER SMOKE: PASS\n";
echo "Fresh schema, retry-safe 1.2.3 deltas, global username/admin creation, and migration parity verified on MySQL.\n";
