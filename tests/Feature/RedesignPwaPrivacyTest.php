<?php

namespace Tests\Feature;

use Tests\TestCase;

class RedesignPwaPrivacyTest extends TestCase
{
    public function test_manifest_and_offline_shell_are_present(): void
    {
        $manifestPath = public_path('manifest.webmanifest');
        $this->assertFileExists($manifestPath);
        $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('./', $manifest['start_url']);
        $this->assertSame('./', $manifest['scope']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertFileExists(public_path('offline.html'));
    }

    public function test_service_worker_caches_only_explicit_public_static_assets(): void
    {
        $worker = (string) file_get_contents(public_path('service-worker.js'));

        foreach ([
            './assets/app.css',
            './assets/redesign.css',
            './assets/redesign-compat.css',
            './assets/platform-themes.css',
            './assets/tenant-themes.css',
            './assets/redesign.js',
            './assets/branding/private-gather-logo.png',
            './offline.html',
        ] as $publicAsset) {
            $this->assertStringContainsString($publicAsset, $worker);
        }

        foreach ([
            'messages/',
            'community/',
            'profile',
            'storage/',
            'uploads/',
            '.env',
            'install/',
        ] as $privatePath) {
            $this->assertStringNotContainsString("'./{$privatePath}", $worker, "Private path {$privatePath} must not enter the static PWA cache list");
        }

        $this->assertStringContainsString("request.mode === 'navigate'", $worker);
        $this->assertStringContainsString("fetch(request).catch", $worker);
    }
}
