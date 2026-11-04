<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SelfHostedEditionTest extends TestCase
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

    public function test_private_self_hosted_site_requires_login_but_auth_pages_remain_available(): void
    {
        $this->get('http://platform.test/')
            ->assertRedirect('http://platform.test/login');

        $this->get('http://platform.test/login')->assertOk();
        $this->get('http://platform.test/admin/login')->assertOk();
    }

    public function test_authenticated_member_sees_single_self_hosted_tenant(): void
    {
        $member = $this->createUser('member@example.test');
        $this->tenant->users()->attach($member->id, ['role' => 'member', 'status' => 'active']);

        $this->actingAs($member)
            ->get('http://platform.test/')
            ->assertOk()
            ->assertSee('Self Hosted Club');
    }

    public function test_self_hosted_edition_disables_create_another_website_flow(): void
    {
        $owner = $this->createUser('owner@example.test', true);
        $this->tenant->users()->attach($owner->id, ['role' => 'owner', 'status' => 'active']);

        $this->actingAs($owner)
            ->get('http://platform.test/my-organizations/create')
            ->assertNotFound();

        $this->actingAs($owner)
            ->get('http://platform.test/my-organizations')
            ->assertRedirect();
    }

    public function test_approval_registration_creates_pending_member_for_the_single_organization(): void
    {
        $response = $this->post('http://platform.test/register', [
            'name' => 'Pending Member',
            'display_name' => 'Pending Member',
            'email' => 'pending@example.test',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'password' => 'Password1234',
            'password_confirmation' => 'Password1234',
            'adult' => '1',
            'terms' => '1',
            'privacy' => '1',
        ]);

        $user = User::query()->where('email', 'pending@example.test')->firstOrFail();

        $response->assertRedirect('http://platform.test/login');
        $this->assertSame('pending', $user->status);
        $this->assertGuest();
        $this->assertDatabaseHas('tenant_users', [
            'tenant_id' => $this->tenant->id,
            'user_id' => $user->id,
            'role' => 'member',
            'status' => 'active',
        ]);
    }

    public function test_disabled_registration_is_not_publicly_accessible(): void
    {
        config(['edition.self_hosted.registration' => 'disabled']);

        $this->get('http://platform.test/register')
            ->assertRedirect('http://platform.test/login');
    }

    private function createUser(string $email, bool $admin = false): User
    {
        return User::create([
            'name' => 'Test User',
            'display_name' => 'Test User',
            'email' => $email,
            'password' => 'Password1234',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'status' => 'active',
            'is_platform_admin' => $admin,
            'adult_confirmed_at' => now(),
            'terms_accepted_at' => now(),
            'privacy_accepted_at' => now(),
            'privacy_version' => '1.0',
        ]);
    }
}
