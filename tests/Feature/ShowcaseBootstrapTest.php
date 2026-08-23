<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Tenant;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowcaseBootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_hosted_showcase_seeds_ten_fictional_clubs_and_twenty_future_events_with_local_media(): void
    {
        config([
            'platform.showcase_content' => true,
            'platform.root_domain' => 'privategather.test',
        ]);

        $this->seed(DatabaseSeeder::class);

        $showcaseTenants = Tenant::query()->get()->filter(
            fn (Tenant $tenant): bool => (bool) data_get($tenant->settings, 'showcase_content', false)
        );

        $this->assertCount(10, $showcaseTenants);
        $this->assertSame(20, Event::query()->whereIn('tenant_id', $showcaseTenants->modelKeys())->count());

        foreach ($showcaseTenants as $tenant) {
            $this->assertTrue((bool) data_get($tenant->settings, 'marketplace_enabled'));
            $this->assertSame('/assets/showcase/club-night.svg', data_get($tenant->settings, 'cover_image_path'));
            $this->assertSame(2, $tenant->events()->count());
        }

        Event::query()->whereIn('tenant_id', $showcaseTenants->modelKeys())->get()->each(function (Event $event): void {
            $this->assertSame('published', $event->status);
            $this->assertSame('public', $event->visibility);
            $this->assertTrue($event->starts_at->isFuture());
            $this->assertNotEmpty($event->cover_image_path);
            $this->assertStringStartsWith('/assets/showcase/', $event->cover_image_path);
            $this->assertNotEmpty($event->gallery);
        });

        foreach ([
            'platform-hero.svg',
            'club-night.svg',
            'event-night.svg',
            'event-social.svg',
            'community.svg',
        ] as $asset) {
            $path = public_path('assets/showcase/'.$asset);
            $this->assertFileExists($path);
            $this->assertGreaterThan(0, filesize($path));
        }
    }

    public function test_showcase_seed_is_idempotent(): void
    {
        config([
            'platform.showcase_content' => true,
            'platform.root_domain' => 'privategather.test',
        ]);

        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $showcaseTenants = Tenant::query()->get()->filter(
            fn (Tenant $tenant): bool => (bool) data_get($tenant->settings, 'showcase_content', false)
        );

        $this->assertCount(10, $showcaseTenants);
        $this->assertSame(20, Event::query()->whereIn('tenant_id', $showcaseTenants->modelKeys())->count());
    }
}
