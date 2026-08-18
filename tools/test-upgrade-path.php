<?php

declare(strict_types=1);

require __DIR__.'/../app/Support/UpgradePath.php';

use App\Support\UpgradePath;

$valid = ['VERSION', 'app/Http/Controllers/Test.php', 'database/migrations/2026_01_01_test.php', 'public/assets/app.css', 'vendor/example/file.php'];
$invalid = ['../.env', '.env', 'storage/app/installed.lock', 'storage/logs/test.log', 'install/index.php', '/../../etc/passwd', 'public/uploads/member.jpg', 'public/storage/private.jpg', 'C:/windows/test'];

foreach ($valid as $path) {
    try { UpgradePath::normalize($path); } catch (Throwable $e) { fwrite(STDERR, "Expected valid: {$path} :: {$e->getMessage()}\n"); exit(1); }
}
foreach ($invalid as $path) {
    try { UpgradePath::normalize($path); fwrite(STDERR, "Expected invalid: {$path}\n"); exit(1); } catch (Throwable) {}
}

echo "UPGRADE PATH TESTS: PASS\n";
