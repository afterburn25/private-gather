<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SelfHostedEditionTestMemberApprovalTest extends TestCase
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

    public function test_owner_can_review_and_activate_pending_member(): void
    {
        $owner = $this->createUser('owner@example.test');
        $pending = $this->createUser('pending@example.test', 'pending');
        $this->tenant->users()->attach($owner->id, ['role' => 'owner', 'status' => 'active']);
        $this->tenant->users()->attach($pending->id, ['role' => 'member', 'status' => 'active']);

        $this->actingAs($owner)
            ->get('http://platform.test/manage/members')
            ->assertOk()
            ->assertSee('pending@example.test')
            ->assertSee('Pending');

        $this->actingAs($owner)
            ->patch('http://platform.test/manage/members/'.$pending->id, ['status' => 'active'])
            ->assertRedirect();

        $this->assertSame('active', $pending->fresh()->status);
    }

    public function test_manager_can_approve_member_but_regular_member_cannot_manage_accounts(): void
    {
        $manager = $this->createUser('manager@example.test');
        $member = $this->createUser('member@example.test');
        $pending = $this->createUser('pending@example.test', 'pending');
        $this->tenant->users()->attach($manager->id, ['role' => 'manager', 'status' => 'active']);
        $this->tenant->users()->attach($member->id, ['role' => 'member', 'status' => 'active']);
        $this->tenant->users()->attach($pending->id, ['role' => 'member', 'status' => 'active']);

        $this->actingAs($manager)
            ->patch('http://platform.test/manage/members/'.$pending->id, ['status' => 'active'])
            ->assertRedirect();
        $this->assertSame('active', $pending->fresh()->status);

        $this->actingAs($member)
            ->get('http://platform.test/manage/members')
            ->assertForbidden();
    }

    public function test_manager_cannot_change_user_outside_self_hosted_tenant(): void
    {
        $manager = $this->createUser('manager@example.test');
        $outsider = $this->createUser('outside@example.test', 'pending');
        $this->tenant->users()->attach($manager->id, ['role' => 'manager', 'status' => 'active']);

        $this->actingAs($manager)
            ->patch('http://platform.test/manage/members/'.$outsider->id, ['status' => 'active'])
            ->assertNotFound();

        $this->assertSame('pending', $outsider->fresh()->status);
    }

    public function test_member_screen_cannot_change_owner_or_staff_accounts(): void
    {
        $owner = $this->createUser('owner@example.test');
        $staff = $this->createUser('staff@example.test');
        $this->tenant->users()->attach($owner->id, ['role' => 'owner', 'status' => 'active']);
        $this->tenant->users()->attach($staff->id, ['role' => 'staff', 'status' => 'active']);

        $this->actingAs($owner)
            ->patch('http://platform.test/manage/members/'.$staff->id, ['status' => 'suspended'])
            ->assertNotFound();

        $this->actingAs($owner)
            ->patch('http://platform.test/manage/members/'.$owner->id, ['status' => 'suspended'])
            ->assertNotFound();

        $this->assertSame('active', $staff->fresh()->status);
        $this->assertSame('active', $owner->fresh()->status);
    }

    private function createUser(string $email, string $status = 'active'): User
    {
        return User::create([
            'name' => 'Test User',
            'display_name' => 'Test User',
            'email' => $email,
            'password' => 'Password1234',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'status' => $status,
            'is_platform_admin' => false,
            'adult_confirmed_at' => now(),
            'terms_accepted_at' => now(),
            'privacy_accepted_at' => now(),
            'privacy_version' => '1.0',
        ]);
    }
}
