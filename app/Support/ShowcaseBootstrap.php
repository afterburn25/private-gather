<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class ShowcaseBootstrap
{
    public static function runPending(): void
    {
        if (! Edition::isHosted()) {
            return;
        }

        $marker = storage_path('app/showcase-bootstrap.pending');
        if (! is_file($marker)) {
            return;
        }

        try {
            if (! Schema::hasTable('tenants') || ! Schema::hasTable('events')) {
                return;
            }

            config(['platform.showcase_content' => true]);
            $app = app();
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

            @file_put_contents(
                storage_path('app/showcase-bootstrap.done'),
                'completed '.gmdate('c').PHP_EOL,
                LOCK_EX
            );
            @unlink($marker);
        } catch (Throwable $e) {
            Log::error('Private Gather showcase bootstrap failed.', ['error' => $e->getMessage()]);
        }
    }
}
