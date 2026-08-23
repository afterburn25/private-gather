<?php

declare(strict_types=1);

$base = dirname(__DIR__);
$errors = [];
$requireFile = static function (string $path) use ($base, &$errors): string {
    $full = $base.'/'.$path;
    if (!is_file($full)) { $errors[] = 'Missing required file: '.$path; return ''; }
    return (string) file_get_contents($full);
};

$providers = $requireFile('bootstrap/providers.php');
$productProvider = $requireFile('app/Providers/ProductCompletionServiceProvider.php');
$trustProvider = $requireFile('app/Providers/TrustOperationsServiceProvider.php');
$routes = $requireFile('routes/web.php');
$completionMigration = $requireFile('database/migrations/2026_08_23_010000_create_product_completion_suite.php');
$installer = $requireFile('install/product-completion-schema.php');
$serviceWorker = $requireFile('public/service-worker.js');
$clientJs = $requireFile('public/assets/redesign.js');
$env = $requireFile('.env.example');
$workflow = $requireFile('.github/workflows/private-gather-ci.yml');

$assert = static function (bool $ok, string $message) use (&$errors): void { if (!$ok) $errors[] = $message; };

$assert(str_contains($providers, 'ProductCompletionServiceProvider::class'), 'Product completion provider is not registered.');
$assert(str_contains($providers, 'TrustOperationsServiceProvider::class'), 'Trust operations provider is not registered.');
$assert(str_contains($productProvider, "Route::middleware('web')"), 'Completion routes must be wrapped in the Laravel web boundary.');
$assert(str_contains($productProvider, 'CommerceDashboardController::class'), 'Dedicated commerce dashboard route is missing.');
$assert(str_contains($trustProvider, 'EnsurePlatformAdmin::class'), 'Trust escalation routes must require platform admin authorization.');
$assert(str_contains($routes, "name('cms.sections.move')"), 'Accessible CMS section ordering route is missing.');

foreach (['onboarding_progress','favorites','saved_searches','user_connections','user_privacy_settings','reviews','marketing_contacts','marketplace_ledger_entries','payouts','refunds','membership_subscriptions','domain_orders','push_subscriptions','trust_cases'] as $table) {
    $assert(str_contains($completionMigration, "Schema::create('{$table}'"), 'Completion migration missing table '.$table.'.');
    $assert(str_contains($installer, 'CREATE TABLE IF NOT EXISTS '.$table), 'Browser installer missing table '.$table.'.');
}
$assert(!str_contains($completionMigration, "Schema::create('user_blocks'"), 'Completion migration must reuse the established user_blocks schema.');

foreach (['403','404','419','429','500','503'] as $status) $requireFile('resources/views/errors/'.$status.'.blade.php');
$requireFile('resources/views/errors/_private-gather.blade.php');
$requireFile('resources/views/tenant/manage/events/_experience.blade.php');
$requireFile('resources/views/tenant/manage/commerce.blade.php');

$assert(str_contains($serviceWorker, "addEventListener('push'"), 'Service worker push presentation is missing.');
$assert(str_contains($serviceWorker, "addEventListener('notificationclick'"), 'Service worker notification navigation is missing.');
$assert(str_contains($clientJs, 'pushManager.subscribe'), 'Browser push enrollment is missing.');
$assert(str_contains($clientJs, 'X-CSRF-TOKEN'), 'Push enrollment must use CSRF protection.');

foreach (['DOMAIN_REGISTRAR_ENDPOINT','DOMAIN_REGISTRAR_TOKEN','WEBPUSH_PUBLIC_KEY','WEBPUSH_PRIVATE_KEY','OBSERVABILITY_DSN','CDN_URL'] as $key) {
    $assert(str_contains($env, $key.'='), '.env.example missing optional production setting '.$key.'.');
}
$assert(str_contains($env, 'SESSION_ENCRYPT=true'), 'Session encryption must remain enabled in the production example.');
$assert(str_contains($workflow, 'verify-product-completion.php'), 'CI does not execute the product completion verifier.');

if ($errors) {
    fwrite(STDERR, "PRODUCT COMPLETION VERIFY: FAIL\n- ".implode("\n- ", $errors)."\n");
    exit(1);
}

echo "PRODUCT COMPLETION VERIFY: PASS\n";
echo "Completion schema parity, web boundaries, privacy-safe push, Club OS commerce, rich events, accessible builder, trust operations and production error surfaces are structurally present.\n";
