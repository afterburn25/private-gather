<?php

declare(strict_types=1);

/** @return list<string> */
function installer_showcase_required_assets(): array
{
    return [
        'public/assets/app.css',
        'public/assets/redesign.css',
        'public/assets/platform-themes.css',
        'public/assets/tenant-themes.css',
        'public/assets/product-completion.css',
        'public/assets/admin.css',
        'public/assets/redesign.js',
        'public/assets/branding/private-gather-logo.png',
        'public/assets/showcase/platform-hero.svg',
        'public/assets/showcase/club-night.svg',
        'public/assets/showcase/event-night.svg',
        'public/assets/showcase/event-social.svg',
        'public/assets/showcase/community.svg',
    ];
}

function installer_showcase_assets_present(): bool
{
    $base = installer_base_path();
    foreach (installer_showcase_required_assets() as $relative) {
        $path = $base.'/'.$relative;
        if (! is_file($path) || filesize($path) === 0) {
            return false;
        }
    }

    return true;
}

function installer_set_env_value(string $key, string $value): void
{
    $path = installer_base_path().'/.env';
    $contents = (string) @file_get_contents($path);
    if ($contents === '') {
        throw new RuntimeException('Unable to update the installation environment.');
    }

    $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
    if (preg_match($pattern, $contents)) {
        $contents = (string) preg_replace($pattern, $key.'='.$value, $contents, 1);
    } else {
        $contents = rtrim($contents).PHP_EOL.$key.'='.$value.PHP_EOL;
    }

    if (file_put_contents($path, $contents, LOCK_EX) === false) {
        throw new RuntimeException('Unable to update '.$key.' in .env.');
    }
    @chmod($path, 0600);
}

function installer_seed_hosted_showcase(array $input): void
{
    if (($input['edition'] ?? 'hosted') !== 'hosted') {
        return;
    }

    installer_set_env_value('PRIVATE_GATHER_SHOWCASE_CONTENT', 'true');
    installer_set_env_value('APP_INSTALLED', 'false');

    try {
        $base = installer_base_path();
        $autoload = $base.'/vendor/autoload.php';
        $bootstrap = $base.'/bootstrap/app.php';
        if (! is_file($autoload) || ! is_file($bootstrap)) {
            throw new RuntimeException('Unable to initialize showcase content because Laravel bootstrap files are missing.');
        }

        require_once $autoload;
        $app = require $bootstrap;
        $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();
        config(['platform.showcase_content' => true]);

        foreach ([
            \Database\Seeders\ShowcaseContentSeeder::class,
            \Database\Seeders\ShowcaseExperienceSeeder::class,
            \Database\Seeders\ShowcasePresentationSeeder::class,
        ] as $seederClass) {
            $seeder = $app->make($seederClass);
            if (method_exists($seeder, 'setContainer')) {
                $seeder->setContainer($app);
            }
            $seeder->run();
        }

        installer_set_env_value('APP_INSTALLED', 'true');
    } catch (Throwable $e) {
        try {
            installer_set_env_value('APP_INSTALLED', 'false');
        } catch (Throwable) {
        }
        throw $e;
    }
}
