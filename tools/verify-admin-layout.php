<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$expect = static function (bool $ok, string $message) use (&$failures): void {
    if (! $ok) {
        $failures[] = $message;
    }
};

$layoutPath = $root.'/resources/views/layouts/admin.blade.php';
$cssPath = $root.'/public/assets/admin.css';
$expect(is_file($layoutPath), 'Dedicated admin Blade layout is missing');
$expect(is_file($cssPath), 'Dedicated admin stylesheet is missing');

$protectedViews = [
    'resources/views/admin/dashboard.blade.php',
    'resources/views/admin/platform-content.blade.php',
    'resources/views/admin/system-health.blade.php',
    'resources/views/admin/moderation/index.blade.php',
    'resources/views/admin/plans/index.blade.php',
    'resources/views/admin/tenants/index.blade.php',
    'resources/views/admin/upgrades/index.blade.php',
    'resources/views/admin/users/index.blade.php',
];

foreach ($protectedViews as $relative) {
    $path = $root.'/'.$relative;
    $content = is_file($path) ? (string) file_get_contents($path) : '';
    $expect($content !== '', $relative.' is missing');
    $expect(str_contains($content, "@extends('layouts.admin')"), $relative.' does not use the dedicated admin layout');
    $expect(! str_contains($content, "@extends('layouts.app')"), $relative.' still inherits the public website layout');
}

$layout = is_file($layoutPath) ? (string) file_get_contents($layoutPath) : '';
$css = is_file($cssPath) ? (string) file_get_contents($cssPath) : '';
$expect(str_contains($layout, "route('admin.home')"), 'Admin layout dashboard navigation is missing');
$expect(str_contains($layout, "route('admin.users.index')"), 'Admin layout user navigation is missing');
$expect(str_contains($layout, "route('admin.tenants.index')"), 'Admin layout organization navigation is missing');
$expect(str_contains($layout, "route('admin.moderation.index')"), 'Admin layout moderation navigation is missing');
$expect(str_contains($layout, "route('admin.upgrades.index')"), 'Admin layout update-center navigation is missing');
$expect(str_contains($layout, "route('admin.logout')"), 'Admin layout sign-out control is missing');
$expect(str_contains($layout, '/assets/admin.css'), 'Admin layout does not load its dedicated stylesheet');
$expect(str_contains($layout, 'View public website'), 'Admin layout lacks an explicit public-site escape link');
$expect(str_contains($css, '.pg-admin-shell'), 'Admin stylesheet lacks the backend shell');
$expect(str_contains($css, '.pg-admin-sidebar'), 'Admin stylesheet lacks the backend sidebar');
$expect(str_contains($css, '.pg-admin-toolbar'), 'Admin stylesheet lacks the backend toolbar');

if ($failures !== []) {
    fwrite(STDERR, "ADMIN LAYOUT VERIFY: FAIL\n - ".implode("\n - ", $failures)."\n");
    exit(1);
}

echo "ADMIN LAYOUT VERIFY: PASS\n";
