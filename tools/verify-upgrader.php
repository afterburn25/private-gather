<?php

declare(strict_types=1);

$base = dirname(__DIR__);
$required = [
    'VERSION',
    'config/release.php',
    'config/upgrades.php',
    'app/Models/PlatformUpgrade.php',
    'app/Http/Controllers/Admin/AdminAuthController.php',
    'app/Http/Controllers/Admin/UpgradeController.php',
    'app/Http/Middleware/EnsurePlatformAdmin.php',
    'app/Services/Upgrade/UpgradePackage.php',
    'app/Services/Upgrade/UpgradeService.php',
    'app/Services/Upgrade/DatabaseBackupService.php',
    'app/Support/UpgradePath.php',
    'database/migrations/2026_08_16_190000_create_platform_upgrades_table.php',
    'resources/views/admin/upgrades/index.blade.php',
    'resources/views/admin/auth/login.blade.php',
];
foreach ($required as $file) {
    if (! is_file($base.'/'.$file)) {
        fwrite(STDERR, "Missing: {$file}\n"); exit(1);
    }
}

$routes = (string) file_get_contents($base.'/routes/web.php');
foreach (["prefix('admin')", "Route::get('/upgrades'", 'EnsurePlatformAdmin', 'admin.login'] as $needle) {
    if (! str_contains($routes, $needle)) { fwrite(STDERR, "Route marker missing: {$needle}\n"); exit(1); }
}

$service = (string) file_get_contents($base.'/app/Services/Upgrade/UpgradeService.php');
foreach (['backupFiles', 'databaseBackup->create', "Artisan::call('down')", "Artisan::call('migrate'", 'applyFiles', "Artisan::call('optimize:clear')", "Artisan::call('up')", 'restoreFiles'] as $needle) {
    if (! str_contains($service, $needle)) { fwrite(STDERR, "Updater marker missing: {$needle}\n"); exit(1); }
}

$schema = (string) file_get_contents($base.'/install/schema.sql');
if (! str_contains($schema, 'CREATE TABLE IF NOT EXISTS platform_upgrades')) { fwrite(STDERR, "Fresh install schema lacks platform_upgrades.\n"); exit(1); }

if (trim((string) file_get_contents($base.'/VERSION')) === '') { fwrite(STDERR, "VERSION is empty.\n"); exit(1); }

echo "UPGRADER VERIFY: PASS\n";
