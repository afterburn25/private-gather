<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$expect = static function (bool $condition, string $message) use (&$failures): void {
    if (! $condition) $failures[] = $message;
};

$expect(trim((string) @file_get_contents($root.'/VERSION')) !== '', 'VERSION must not be empty');
$expect(is_file($root.'/public/assets/branding/private-gather-logo.png'), 'official Private Gather logo missing');
$expect(str_contains((string) @file_get_contents($root.'/config/app.php'), "'Private Gather'"), 'app name not branded');
$expect(str_contains((string) @file_get_contents($root.'/config/release.php'), "privategather/private-gather"), 'release product id not branded');
$expect(str_contains((string) @file_get_contents($root.'/config/platform.php'), '_privategather-verification'), 'domain verification prefix not branded');
$expect(str_contains((string) @file_get_contents($root.'/install/index.php'), 'Install Private Gather'), 'installer not branded');
$expect(str_contains((string) @file_get_contents($root.'/resources/views/layouts/app.blade.php'), 'platform-brand-logo'), 'site layout does not use official brand logo');
$expect(str_contains((string) @file_get_contents($root.'/resources/views/admin/auth/login.blade.php'), 'private-gather-logo.png'), 'admin login logo missing');
$expect(str_contains((string) @file_get_contents($root.'/app/Services/PlatformContent.php'), "'brand_name' => 'Private Gather'"), 'CMS defaults not branded');
$expect(str_contains((string) @file_get_contents($root.'/composer.json'), 'privategather/private-gather'), 'composer identity not branded');

if ($failures) {
    fwrite(STDERR, "PRIVATE GATHER BRAND VERIFY: FAIL\n - ".implode("\n - ", $failures)."\n");
    exit(1);
}

fwrite(STDOUT, "PRIVATE GATHER BRAND VERIFY: PASS\n");
