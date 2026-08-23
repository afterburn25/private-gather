<?php

namespace Tests\Feature;

use App\Models\CmsPage;
use App\Models\Event;
use App\Models\Tenant;
use App\Models\TenantBranding;
use App\Services\PlatformContent;
use App\Support\ThemeCatalog;
use Database\Seeders\ShowcaseContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RedesignThemeAndShowcaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_official_logo_and_theme_catalogs_are_separate_and_complete(): void
    {
        $platformThemes = ThemeCatalog::platformThemes();
        $tenantThemes = ThemeCatalog::tenantThemes();

        $this->assertCount(5, $platformThemes);
        $this->assertCount(10, $tenantThemes);
        $this->assertSame([], array_values(array_intersect(array_keys($platformThemes), array_keys($tenantThemes))));
        $this->assertSame('/assets/branding/private-gather-logo.png', PlatformContent::OFFICIAL_LOGO);

        $logo = public_path('assets/branding/private-gather-logo.png');
        $this->assertFileExists($logo);
        $this->assertSame(
            '4440eaf43a929325d859bf6fe9dc922f0bd0de7eb0bb240abdd20e24a04ceda2',
            hash_file('sha256', $logo)
        );

        $content = app(PlatformContent::class)->all();
        $this->assertSame(PlatformContent::OFFICIAL_LOGO, $content['logo_url']);
        $this->assertArrayHasKey($content['theme_preset'], $platformThemes);
    }

    public function test_tenant_public_site_has_its_own_layout_and_theme_assets(): void
    {
        $tenantLayout = (string) file_get_contents(resource_path('views/layouts/tenant-site.blade.php'));
        $platformLayout = (string) file_get_contents(resource_path('views/layouts/app.blade.php'));
        $tenantCss = (string) file_get_contents(public_path('assets/tenant-themes.css'));
        $platformCss = (string) file_get_contents(public_path('assets/platform-themes.css'));

        $this->assertStringContainsString('data-tenant-theme', $tenantLayout);
        $this->assertStringContainsString('tenant-theme-', $tenantLayout);
        $this->assertStringContainsString('/assets/tenant-themes.css', $tenantLayout);
        $this->assertStringNotContainsString('platform-theme-', $tenantLayout);

        $this->assertStringContainsString('platform-theme-', $platformLayout);
        $this->assertStringContainsString('/assets/platform-themes.css', $platformLayout);
        $this->assertStringNotContainsString('/assets/tenant-themes.css', $platformLayout);

        foreach (array_keys(ThemeCatalog::tenantThemes()) as $key) {
            $this->assertStringContainsString('.tenant-theme-'.$key, $tenantCss, "Missing tenant theme CSS for {$key}");
        }
        foreach (array_keys(ThemeCatalog::platformThemes()) as $key) {
            $this->assertStringContainsString('.platform-theme-'.$key, $platformCss, "Missing platform theme CSS for {$key}");
        }
    }

    public function test_showcase_seeder_creates_ten_distinct_clubs_and_twenty_future_events_idempotently(): void
    {
        $seeder = app(ShowcaseContentSeeder::class);
        $seeder->run();
        $seeder->run();

        $showcaseTenants = Tenant::query()->get()->filter(
            fn (Tenant $tenant): bool => (bool) data_get($tenant->settings, 'showcase_content', false)
        )->values();

        $this->assertCount(10, $showcaseTenants);
        $this->assertTrue($showcaseTenants->every(fn (Tenant $tenant): bool =>
            $tenant->status === 'active'
            && $tenant->type === Tenant::TYPE_CLUB
            && (bool) data_get($tenant->settings, 'marketplace_enabled', false)
        ));

        $tenantIds = $showcaseTenants->pluck('id');
        $events = Event::query()->whereIn('tenant_id', $tenantIds)->get();
        $this->assertCount(20, $events);
        $this->assertTrue($events->every(fn (Event $event): bool =>
            $event->status === 'published'
            && $event->visibility === 'public'
            && $event->starts_at->isFuture()
        ));

        $brandings = TenantBranding::query()->whereIn('tenant_id', $tenantIds)->get();
        $this->assertCount(10, $brandings);
        $presets = $brandings->map(fn (TenantBranding $branding): string => ThemeCatalog::tenantPresetFromTheme($branding->theme))->sort()->values()->all();
        $expectedPresets = collect(array_keys(ThemeCatalog::tenantThemes()))->sort()->values()->all();
        $this->assertSame($expectedPresets, $presets);

        $this->assertSame(10, CmsPage::query()->whereIn('tenant_id', $tenantIds)->where('is_homepage', true)->where('status', 'published')->count());
        $this->assertTrue($showcaseTenants->every(fn (Tenant $tenant): bool => $tenant->primaryDomain()->where('status', 'active')->exists()));
    }
}
