<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SelfHostedEditionTestLocalAdminScopeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'http://platform.test',
            'edition.name' => 'self_hosted',
            'edition.self_hosted.visibility' => 'private',
            'edition.self_hosted.registration' => 'approval',
            'platform.root_domain' => 'platform.test',
            'platform.central_domains' => ['platform.test'],
        ]);

        $this->tenant = Tenant::create([
            'name' => 'Self Hosted Club',
            'slug' => 'self-hosted-club',
            'type' => Tenant::TYPE_PRIVATE_HOST,
            'status' => 'active',
            'plan' => 'self_hosted',
            'settings' => ['marketplace_enabled' => false, 'self_hosted' => true],
        ]);

        config(['edition.self_hosted.tenant_id' => $this->tenant->id]);
    }

    public function test_local_user_admin_lists_only_users_in_configured_organization(): void
    {
        $admin = $this->createUser('admin@example.test', true);
        $local = $this->createUser('local-member@example.test');
        $outsider = $this->createUser('outside-import@example.test');

        $this->tenant->users()->attach($admin->id, ['role' => 'owner', 'status' => 'active']);
        $this->tenant->users()->attach($local->id, ['role' => 'member', 'status' => 'active']);

        $this->actingAs($admin)
            ->get('http://platform.test/admin/users')
            ->assertOk()
            ->assertSee('local-member@example.test')
            ->assertDontSee('outside-import@example.test');

        $this->actingAs($admin)
            ->patch('http://platform.test/admin/users/'.$outsider->id, [
                'status' => 'suspended',
                'is_platform_admin' => '0',
            ])
            ->assertNotFound();

        $this->assertSame('active', $outsider->fresh()->status);
    }

    private function createUser(string $email, bool $platformAdmin = false): User
    {
        return User::create([
            'name' => 'Test User',
            'display_name' => 'Test User',
            'email' => $email,
            'password' => 'Password1234',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'status' => 'active',
            'is_platform_admin' => $platformAdmin,
            'adult_confirmed_at' => now(),
            'terms_accepted_at' => now(),
            'privacy_accepted_at' => now(),
            'privacy_version' => '1.0',
        ]);
    }
}
