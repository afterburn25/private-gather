<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];

$read = static function (string $relative) use ($root, &$errors): string {
    $path = $root.'/'.$relative;
    if (! is_file($path)) {
        $errors[] = 'Missing '.$relative;
        return '';
    }
    return (string) file_get_contents($path);
};

$databaseSeeder = $read('database/seeders/DatabaseSeeder.php');
$contentSeeder = $read('database/seeders/ShowcaseContentSeeder.php');
$presentationSeeder = $read('database/seeders/ShowcasePresentationSeeder.php');
$hostedInstaller = $read('install/index.php');
$showcaseInstaller = $read('install/showcase.php');
$provider = $read('app/Providers/AppServiceProvider.php');
$bootstrap = $read('app/Support/ShowcaseBootstrap.php');
$platformContent = $read('app/Services/PlatformContent.php');
$home = $read('resources/views/platform/home.blade.php');
$events = $read('resources/views/platform/events.blade.php');
$organizations = $read('resources/views/platform/organizations.blade.php');

foreach ([
    'public/assets/showcase/platform-hero.svg',
    'public/assets/showcase/club-night.svg',
    'public/assets/showcase/event-night.svg',
    'public/assets/showcase/event-social.svg',
    'public/assets/showcase/community.svg',
] as $asset) {
    $path = $root.'/'.$asset;
    if (! is_file($path) || filesize($path) < 100) {
        $errors[] = 'Missing or empty packaged showcase asset '.$asset;
    }
}

foreach ([
    'ShowcaseContentSeeder::class',
    'ShowcaseExperienceSeeder::class',
    'ShowcasePresentationSeeder::class',
] as $needle) {
    if (! str_contains($databaseSeeder, $needle)) {
        $errors[] = 'DatabaseSeeder does not invoke '.$needle;
    }
}

$expectedSlugs = [
    'velvet-room', 'eclipse-house', 'desire-lounge', 'oasis-private-club', 'noir-society',
    'garden-society', 'ember-room', 'maison-rouge', 'villa-prive', 'aurora-social',
];
foreach ($expectedSlugs as $slug) {
    if (! str_contains($contentSeeder, "'slug'=>'{$slug}'")) {
        $errors[] = 'ShowcaseContentSeeder missing fictional club '.$slug;
    }
}
if (substr_count($contentSeeder, "'events'=>[[") !== 10) {
    $errors[] = 'ShowcaseContentSeeder must define two-event fixtures for exactly 10 fictional clubs.';
}

foreach (['cover_image_path', '/assets/showcase/club-night.svg', '/assets/showcase/event-night.svg', '/assets/showcase/event-social.svg', 'gallery'] as $needle) {
    if (! str_contains($presentationSeeder, $needle)) {
        $errors[] = 'ShowcasePresentationSeeder missing '.$needle;
    }
}

if (! str_contains($hostedInstaller, "require __DIR__.'/showcase.php'")) {
    $errors[] = 'Hosted installer does not load showcase bootstrap helper.';
}
if (! str_contains($hostedInstaller, 'installer_showcase_assets_present()')) {
    $errors[] = 'Hosted installer does not block when packaged public/showcase assets are missing.';
}
if (! str_contains($hostedInstaller, 'installer_seed_hosted_showcase($installInput)')) {
    $errors[] = 'Hosted installer does not seed showcase content before installation completes.';
}

foreach ([
    "installer_set_env_value('PRIVATE_GATHER_SHOWCASE_CONTENT', 'true')",
    "installer_set_env_value('APP_INSTALLED', 'false')",
    "installer_set_env_value('APP_INSTALLED', 'true')",
    'ShowcaseContentSeeder::class',
    'ShowcaseExperienceSeeder::class',
    'ShowcasePresentationSeeder::class',
] as $needle) {
    if (! str_contains($showcaseInstaller, $needle)) {
        $errors[] = 'Installer showcase helper missing '.$needle;
    }
}

if (! str_contains($provider, 'ShowcaseBootstrap::runPending()')) {
    $errors[] = 'Application boot does not execute one-time pending showcase recovery.';
}
foreach (['showcase-bootstrap.pending', 'showcase-bootstrap.done', 'ShowcaseContentSeeder::class', 'ShowcaseExperienceSeeder::class', 'ShowcasePresentationSeeder::class'] as $needle) {
    if (! str_contains($bootstrap, $needle)) {
        $errors[] = 'One-time showcase recovery bootstrap missing '.$needle;
    }
}

foreach (['/assets/showcase/platform-hero.svg', '/assets/showcase/community.svg'] as $needle) {
    if (! str_contains($platformContent, $needle)) {
        $errors[] = 'Platform default content missing '.$needle;
    }
}

if (! str_contains($home, 'cover_image_path') || ! str_contains($events, 'cover_image_path')) {
    $errors[] = 'Platform event surfaces do not render event cover imagery.';
}
$clubImageNeedle = 'data_get($org->settings,\'cover_image_path\')';
if (! str_contains($home, $clubImageNeedle) || ! str_contains($organizations, $clubImageNeedle)) {
    $errors[] = 'Platform organization surfaces do not render showcase club imagery.';
}
if (! str_contains($organizations, 'Fictional showcase')) {
    $errors[] = 'Fictional showcase clubs are not visibly labeled as fictional showcase content.';
}

if ($errors !== []) {
    fwrite(STDERR, "SHOWCASE BOOTSTRAP VERIFY: FAIL\n- ".implode("\n- ", $errors)."\n");
    exit(1);
}

echo "SHOWCASE BOOTSTRAP VERIFY: PASS\n";
echo "10 fictional clubs, 20 event fixtures, packaged local imagery, fresh-install seeding, and one-time recovery wiring are structurally present.\n";
