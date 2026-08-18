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
            'app.url' => 'http://platform.test',
            'platform.root_domain' => 'privategather.test',
            'platform.central_domains' => ['platform.test'],
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

        $this->get('http://platform.test/manage')
            ->assertOk()
            ->assertSee('Demo Club')
            ->assertSeeText('Website & CMS')
            ->assertSee('Preview Website');
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

    public function test_my_sites_can_reopen_management_and_preview_without_tenant_dns(): void
    {
        $owner = $this->createUser('reopen@example.test');

        $this->actingAs($owner)->post('http://platform.test/my-organizations', [
            'name' => 'Preview Club',
            'type' => 'club',
            'subdomain' => 'preview-club',
        ]);

        $tenant = Tenant::query()->where('name', 'Preview Club')->firstOrFail();

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
