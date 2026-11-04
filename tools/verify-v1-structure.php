<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$required = [
    'VERSION',
    'composer.json',
    'routes/web.php',
    'install/schema.sql',
    'app/Services/PlaceholderRenderer.php',
    'app/Services/DomainHealthService.php',
    'app/Services/SystemHealth.php',
    'app/Services/TicketIssuer.php',
    'app/Services/FeatureGate.php',
    'app/Services/PlatformContent.php',
    'app/Models/EventInvitation.php',
    'app/Http/Controllers/Admin/PlatformContentController.php',
    'app/Services/Upgrade/UpgradeService.php',
    'app/Services/Upgrade/DatabaseBackupService.php',
    'app/Contracts/PaymentGateway.php',
    'app/Http/Controllers/CheckoutController.php',
    'app/Http/Controllers/Tenant/CheckinController.php',
    'app/Http/Controllers/Tenant/CmsController.php',
    'app/Http/Controllers/Tenant/DomainController.php',
    'app/Http/Controllers/Admin/ModerationController.php',
    'database/migrations/2026_08_17_001300_add_scale_indexes.php',
    'database/migrations/2026_08_17_001400_seed_v1_defaults.php',
    'database/migrations/2026_08_17_001500_create_event_invitations.php',
    'database/migrations/2026_08_17_001600_create_platform_settings.php',
    'database/migrations/2026_08_17_040000_private_gather_brand_identity.php',
    'public/assets/branding/private-gather-logo.png',
    'config/brand.php',
    'docs/1.0-DEPLOYMENT.md',
    'docs/1.0-ACCEPTANCE-CHECKLIST.md',
];
$errors = [];
foreach ($required as $file) {
    if (! is_file($root.'/'.$file)) {
        $errors[] = 'Missing '.$file;
    }
}
if (trim((string) @file_get_contents($root.'/VERSION')) === '') {
    $errors[] = 'VERSION is empty';
}
$routes = (string) @file_get_contents($root.'/routes/web.php');
$routeMarkers = [
    "name('rsvp.store')",
    "name('checkout.store')",
    "name('cms.pages')",
    "name('domains.index')",
    "name('checkin.index')",
    "name('upgrades.index')",
    "name('moderation.index')",
];
foreach ($routeMarkers as $marker) {
    if (! str_contains($routes, $marker)) {
        $errors[] = 'Route marker missing: '.$marker;
    }
}
$schema = (string) @file_get_contents($root.'/install/schema.sql');
foreach (['profiles','ticket_types','orders','tickets','event_invitations','conversations','event_checkins','reports','plans','tenant_subscriptions','consent_records','tenant_branding','platform_settings'] as $table) {
    if (! str_contains($schema, $table)) {
        $errors[] = 'Fresh schema marker missing: '.$table;
    }
}
if ($errors) {
    fwrite(STDERR, "V1 STRUCTURE FAIL\n- ".implode("\n- ", $errors).PHP_EOL);
    exit(1);
}
echo "V1 STRUCTURE PASS".PHP_EOL;
