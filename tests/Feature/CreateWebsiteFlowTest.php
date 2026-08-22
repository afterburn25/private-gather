<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateWebsiteFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'http://platform.test/private-gather',
            'platform.root_domain' => 'privategather.test',
            'platform.central_domains' => ['platform.test'],
            'platform.tenant_scheme' => 'https',
            'platform.tenant_mount_path' => '/private-gather',
            'platform.wildcard_enabled' => true,
            'platform.wildcard_target' => 'platform.test',
        ]);
    }

    public function test_create_website_normalizes_address_and_opens_management_on_central_host(): void
    {
        $owner = $this->createUser('owner@example.test');

        $response = $this->actingAs($owner)->post('http://platform.test/my-organizations', [
            'name' => 'Demo Club',
            'type' => 'club',
            'subdomain' => 'Demo Club',
        ]);

        $tenant = Tenant::query()->where('name', 'Demo Club')->firstOrFail();

        $response->assertRedirect();
        $response->assertSessionHas('tenant.workspace_id', $tenant->id);
        $this->assertDatabaseHas('tenant_domains', [
            'tenant_id' => $tenant->id,
            'domain' => 'demo-club.privategather.test',
            'is_primary' => true,
        ]);
        $this->assertDatabaseHas('tenant_users', [
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
        ]);
        $this->assertSame('adult_lifestyle', data_get($tenant->settings, 'market'));
        $this->assertTrue((bool) data_get($tenant->settings, 'adult_only'));
        $this->assertSame('approved_or_ticketed', data_get($tenant->settings, 'venue_address_visibility'));
        $this->assertSame(['couple', 'individual'], data_get($tenant->settings, 'membership_profiles'));
        $this->assertTrue((bool) data_get($tenant->settings, 'consent_policy_enabled'));

        $this->get('http://platform.test/manage')
            ->assertOk()
            ->assertSee('Demo Club')
            ->assertSeeText('Website & CMS')
            ->assertSee('Preview Website')
            ->assertSee('https://demo-club.privategather.test/private-gather/', false);
    }

    public function test_organization_creation_provisions_first_class_lifestyle_website_defaults(): void
    {
        $owner = $this->createUser('community-owner@example.test');

        $this->actingAs($owner)->post('http://platform.test/my-organizations', [
            'name' => 'Dallas Social Society',
            'type' => 'organization',
            'subdomain' => 'Dallas Social',
            'city' => 'Dallas',
            'region' => 'Texas',
            'tagline' => 'Private events, community, and connections.',
            'template' => 'velvet',
            'marketplace_enabled' => '1',
        ])->assertRedirect();

        $tenant = Tenant::query()
            ->where('name', 'Dallas Social Society')
            ->with(['branding', 'pages'])
            ->firstOrFail();

        $this->assertSame(Tenant::TYPE_ORGANIZATION, $tenant->type);
        $this->assertSame('Dallas', data_get($tenant->settings, 'city'));
        $this->assertSame('Texas', data_get($tenant->settings, 'region'));
        $this->assertSame('velvet', data_get($tenant->settings, 'template'));
        $this->assertTrue((bool) data_get($tenant->settings, 'marketplace_enabled'));
        $this->assertSame('adult_lifestyle', data_get($tenant->settings, 'market'));
        $this->assertSame('members', data_get($tenant->settings, 'member_directory_visibility'));
        $this->assertSame('members', data_get($tenant->settings, 'event_attendance_visibility'));
        $this->assertSame('age_required_identity_optional', data_get($tenant->settings, 'verification_policy'));
        $this->assertSame('velvet', data_get($tenant->branding?->theme, 'template'));
        $this->assertSame('adult_lifestyle', data_get($tenant->branding?->theme, 'market'));

        foreach (['home', 'about', 'membership', 'first-visit', 'rules', 'privacy', 'contact'] as $slug) {
            $this->assertDatabaseHas('cms_pages', [
                'tenant_id' => $tenant->id,
                'slug' => $slug,
                'status' => 'published',
            ]);
        }

        $this->assertDatabaseHas('cms_navigation_items', [
            'tenant_id' => $tenant->id,
            'location' => 'header',
            'label' => 'Membership',
            'url' => '/page/membership',
        ]);
        $this->assertDatabaseHas('cms_navigation_items', [
            'tenant_id' => $tenant->id,
            'location' => 'header',
            'label' => 'First Visit',
            'url' => '/page/first-visit',
        ]);
        $this->assertDatabaseHas('cms_navigation_items', [
            'tenant_id' => $tenant->id,
            'location' => 'header',
            'label' => 'Privacy',
            'url' => '/page/privacy',
        ]);

        $this->get('http://platform.test/my-organizations?preview='.$tenant->id)
            ->assertOk()
            ->assertSee('Dallas Social Society')
            ->assertSee('Private events, community, and connections.')
            ->assertSee('Dallas')
            ->assertSee('Texas');
    }

    public function test_legacy_organizer_creation_is_normalized_to_organization(): void
    {
        $owner = $this->createUser('legacy-organizer@example.test');

        $this->actingAs($owner)->post('http://platform.test/my-organizations', [
            'name' => 'Legacy Organizer',
            'type' => 'organizer',
            'subdomain' => 'legacy-organizer',
        ])->assertRedirect();

        $this->assertDatabaseHas('tenants', [
            'name' => 'Legacy Organizer',
            'type' => Tenant::TYPE_ORGANIZATION,
        ]);
    }

    public function test_reserved_address_returns_visible_validation_error_instead_of_exception_page(): void
    {
        $owner = $this->createUser('reserved@example.test');

        $this->actingAs($owner)
            ->from('http://platform.test/my-organizations/create')
            ->post('http://platform.test/my-organizations', [
                'name' => 'Admin Club',
                'type' => 'club',
                'subdomain' => 'admin',
            ])
            ->assertRedirect('http://platform.test/my-organizations/create')
            ->assertSessionHasErrors('subdomain');

        $this->assertDatabaseMissing('tenants', ['name' => 'Admin Club']);
    }

    public function test_my_sites_can_reopen_management_preview_and_public_address_without_per_site_dns_records(): void
    {
        $owner = $this->createUser('reopen@example.test');

        $this->actingAs($owner)->post('http://platform.test/my-organizations', [
            'name' => 'Preview Club',
            'type' => 'club',
            'subdomain' => 'preview-club',
        ]);

        $tenant = Tenant::query()->where('name', 'Preview Club')->firstOrFail();

        $this->get('http://platform.test/my-organizations')
            ->assertOk()
            ->assertSee('Manage Website')
            ->assertSee('Open Public Website')
            ->assertSee('https://preview-club.privategather.test/private-gather/', false)
            ->assertSee('*.privategather.test', false);

        $this->get('http://platform.test/my-organizations?workspace='.$tenant->id)
            ->assertRedirect();

        $this->get('http://platform.test/manage')
            ->assertOk()
            ->assertSee('Preview Club');

        $this->get('http://platform.test/my-organizations?preview='.$tenant->id)
            ->assertOk()
            ->assertSee('Website preview:')
            ->assertSee('Preview Club');
    }

    public function test_member_cannot_open_another_owners_workspace(): void
    {
        $owner = $this->createUser('first-owner@example.test');
        $outsider = $this->createUser('outsider@example.test');

        $this->actingAs($owner)->post('http://platform.test/my-organizations', [
            'name' => 'Private Club',
            'type' => 'club',
            'subdomain' => 'private-club',
        ]);

        $tenant = Tenant::query()->where('name', 'Private Club')->firstOrFail();

        $this->actingAs($outsider)
            ->get('http://platform.test/my-organizations?workspace='.$tenant->id)
            ->assertNotFound();
    }

    private function createUser(string $email): User
    {
        return User::create([
            'name' => 'Test Member',
            'display_name' => 'Test Member',
            'email' => $email,
            'password' => 'Password123',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'status' => 'active',
            'adult_confirmed_at' => now(),
            'terms_accepted_at' => now(),
            'privacy_accepted_at' => now(),
            'privacy_version' => '1.0',
        ]);
    }
}
