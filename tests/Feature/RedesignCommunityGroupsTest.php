<?php

namespace Tests\Feature;

use App\Models\CommunityGroup;
use App\Models\CommunityPost;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RedesignCommunityGroupsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.url' => 'http://platform.test',
            'platform.root_domain' => 'platform.test',
            'platform.central_domains' => ['platform.test'],
        ]);
    }

    public function test_group_visibility_and_posts_stay_inside_tenant_and_group_membership(): void
    {
        [$clubA, $domainA, $ownerA] = $this->createClub('velvet.test');
        [$clubB, $domainB, $ownerB] = $this->createClub('other.test');
        $member = $this->createUser('member@example.test');
        $outsider = $this->createUser('outsider@example.test');
        $clubA->users()->attach($member->id, ['role' => 'member', 'status' => 'active']);
        $clubA->users()->attach($outsider->id, ['role' => 'member', 'status' => 'active']);

        $this->actingAs($ownerA)->post('http://'.$domainA.'/community/groups', [
            'name' => 'After Hours Travelers',
            'description' => 'A smaller private circle.',
            'visibility' => 'invite_only',
            'join_policy' => 'invite_only',
            'allow_member_posts' => '1',
        ])->assertRedirect();

        $group = CommunityGroup::where('tenant_id', $clubA->id)->firstOrFail();
        $group->members()->attach($member->id, ['role' => 'member', 'status' => 'active']);

        $this->actingAs($member)->get('http://'.$domainA.'/community/groups/'.$group->id)
            ->assertOk()
            ->assertSee('After Hours Travelers');

        $this->actingAs($member)->post('http://'.$domainA.'/community/groups/'.$group->id.'/posts', [
            'body' => 'Private group post',
        ])->assertRedirect();

        $post = CommunityPost::where('community_group_id', $group->id)->firstOrFail();
        $this->assertSame($clubA->id, $post->tenant_id);

        $this->actingAs($outsider)->get('http://'.$domainA.'/community/groups/'.$group->id)->assertNotFound();
        $this->actingAs($outsider)->post('http://'.$domainA.'/community/posts/'.$post->id.'/comments', [
            'body' => 'Should not be allowed',
        ])->assertNotFound();

        $this->actingAs($ownerB)->get('http://'.$domainB.'/community/groups/'.$group->id)->assertNotFound();
        $this->assertDatabaseMissing('community_comments', ['post_id' => $post->id, 'body' => 'Should not be allowed']);
    }

    public function test_open_and_approval_groups_apply_expected_join_state(): void
    {
        [$club, $domain, $owner] = $this->createClub('join.test');
        $member = $this->createUser('joiner@example.test');
        $club->users()->attach($member->id, ['role' => 'member', 'status' => 'active']);

        $open = CommunityGroup::create([
            'tenant_id' => $club->id,
            'created_by' => $owner->id,
            'name' => 'Open Circle',
            'slug' => 'open-circle',
            'visibility' => 'tenant',
            'join_policy' => 'open',
            'status' => 'active',
            'allow_member_posts' => true,
        ]);
        $approval = CommunityGroup::create([
            'tenant_id' => $club->id,
            'created_by' => $owner->id,
            'name' => 'Approval Circle',
            'slug' => 'approval-circle',
            'visibility' => 'tenant',
            'join_policy' => 'approval',
            'status' => 'active',
            'allow_member_posts' => true,
        ]);

        $this->actingAs($member)->post('http://'.$domain.'/community/groups/'.$open->id.'/join')->assertRedirect();
        $this->assertDatabaseHas('community_group_members', [
            'community_group_id' => $open->id,
            'user_id' => $member->id,
            'status' => 'active',
        ]);

        $this->actingAs($member)->post('http://'.$domain.'/community/groups/'.$approval->id.'/join')->assertRedirect();
        $this->assertDatabaseHas('community_group_members', [
            'community_group_id' => $approval->id,
            'user_id' => $member->id,
            'status' => 'pending',
        ]);

        $this->actingAs($owner)->patch('http://'.$domain.'/community/groups/'.$approval->id.'/members/'.$member->id.'/approve')->assertRedirect();
        $this->assertDatabaseHas('community_group_members', [
            'community_group_id' => $approval->id,
            'user_id' => $member->id,
            'status' => 'active',
        ]);
    }

    private function createClub(string $domain): array
    {
        $tenant = Tenant::create([
            'name' => 'Lifestyle '.str_replace('.test', '', $domain),
            'slug' => str_replace('.', '-', $domain),
            'type' => Tenant::TYPE_CLUB,
            'status' => 'active',
            'plan' => 'starter',
            'settings' => ['marketplace_enabled' => true, 'market' => 'adult_lifestyle', 'adult_only' => true],
        ]);
        TenantDomain::create([
            'tenant_id' => $tenant->id,
            'domain' => $domain,
            'type' => TenantDomain::TYPE_CUSTOM_DOMAIN,
            'is_primary' => true,
            'status' => TenantDomain::STATUS_ACTIVE,
            'verified_at' => now(),
            'ssl_status' => 'active',
            'dns_status' => 'active',
        ]);
        $owner = $this->createUser('owner-'.str_replace('.', '-', $domain).'@example.test');
        $tenant->users()->attach($owner->id, ['role' => 'owner', 'status' => 'active']);

        return [$tenant, $domain, $owner];
    }

    private function createUser(string $email): User
    {
        return User::create([
            'name' => 'Lifestyle Member',
            'display_name' => 'Lifestyle Member',
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
