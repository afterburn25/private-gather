<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CommunityGroup;
use App\Models\CommunityGroupInvite;
use App\Models\CommunityGroupMember;
use App\Models\CommunityPost;
use App\Models\Event;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class V123CommunityRuntimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.url' => 'https://platform.test',
            'edition.name' => 'hosted',
            'platform.root_domain' => 'platform.test',
            'platform.central_domains' => ['platform.test', 'www.platform.test'],
            'platform.tenant_scheme' => 'https',
            'platform.tenant_mount_path' => '',
            'demo.enabled' => false,
        ]);
    }

    public function test_event_and_group_community_routes_render_and_persist_context_scoped_posts(): void
    {
        [$tenant, $member] = $this->tenantFixture();
        $event = Event::create([
            'tenant_id' => $tenant->id,
            'title' => 'Community Night',
            'slug' => 'community-night',
            'visibility' => 'members',
            'rsvp_mode' => 'instant',
            'status' => 'published',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(4),
            'timezone' => 'America/Chicago',
        ]);
        $group = CommunityGroup::create([
            'tenant_id' => $tenant->id,
            'owner_user_id' => $member->id,
            'name' => 'Travel Friends',
            'slug' => 'travel-friends',
            'description' => 'Plan community travel together.',
            'group_type' => 'travel',
            'visibility' => 'private',
            'allow_member_posts' => true,
            'status' => 'active',
        ]);
        CommunityGroupMember::create(['group_id' => $group->id, 'user_id' => $member->id, 'role' => 'owner', 'status' => 'active']);

        $this->actingAs($member)
            ->get('https://club.platform.test/events/'.$event->id.'/community')
            ->assertOk()
            ->assertSee('Community Night');

        $this->actingAs($member)
            ->post('https://club.platform.test/events/'.$event->id.'/community', ['body' => 'Event conversation'])
            ->assertRedirect();
        $this->assertDatabaseHas('community_posts', [
            'tenant_id' => $tenant->id,
            'user_id' => $member->id,
            'event_id' => $event->id,
            'post_type' => 'event',
            'share_to_club_wall' => false,
            'body' => 'Event conversation',
        ]);

        $this->actingAs($member)
            ->get('https://club.platform.test/groups/'.$group->id)
            ->assertOk()
            ->assertSee('Travel Friends');

        $this->actingAs($member)
            ->post('https://club.platform.test/groups/'.$group->id.'/posts', ['body' => 'Group conversation'])
            ->assertRedirect();
        $this->assertDatabaseHas('community_posts', [
            'tenant_id' => $tenant->id,
            'user_id' => $member->id,
            'group_id' => $group->id,
            'post_type' => 'group',
            'share_to_club_wall' => false,
            'body' => 'Group conversation',
        ]);
    }

    public function test_private_group_post_cannot_be_commented_on_by_non_member_using_direct_post_id(): void
    {
        [$tenant, $owner] = $this->tenantFixture('owner');
        $outsider = $this->member($tenant, 'outsider');
        $group = CommunityGroup::create([
            'tenant_id' => $tenant->id,
            'owner_user_id' => $owner->id,
            'name' => 'Private Circle',
            'slug' => 'private-circle',
            'group_type' => 'interest',
            'visibility' => 'private',
            'allow_member_posts' => true,
            'status' => 'active',
        ]);
        CommunityGroupMember::create(['group_id' => $group->id, 'user_id' => $owner->id, 'role' => 'owner', 'status' => 'active']);
        $post = CommunityPost::create([
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'group_id' => $group->id,
            'post_type' => 'group',
            'visibility' => 'members',
            'share_to_club_wall' => false,
            'body' => 'Private group message',
            'status' => 'active',
        ]);

        $this->actingAs($outsider)
            ->post('https://club.platform.test/community/posts/'.$post->id.'/comments', ['body' => 'Should not work'])
            ->assertNotFound();
        $this->assertDatabaseMissing('community_comments', ['post_id' => $post->id, 'user_id' => $outsider->id]);
    }

    public function test_group_invitation_can_be_accepted_by_active_tenant_member(): void
    {
        [$tenant, $owner] = $this->tenantFixture('owner');
        $target = $this->member($tenant, 'invite-target');
        $group = CommunityGroup::create([
            'tenant_id' => $tenant->id,
            'owner_user_id' => $owner->id,
            'name' => 'Invite Only Circle',
            'slug' => 'invite-only-circle',
            'group_type' => 'vip',
            'visibility' => 'private',
            'allow_member_posts' => true,
            'status' => 'active',
        ]);
        CommunityGroupMember::create(['group_id' => $group->id, 'user_id' => $owner->id, 'role' => 'owner', 'status' => 'active']);

        $this->actingAs($owner)
            ->post('https://club.platform.test/groups/'.$group->id.'/invites', ['user_id' => $target->id])
            ->assertRedirect();

        $invite = CommunityGroupInvite::query()->where('group_id', $group->id)->where('user_id', $target->id)->firstOrFail();
        $this->actingAs($target)
            ->post('https://club.platform.test/groups/invites/'.$invite->id.'/respond', ['action' => 'accept'])
            ->assertRedirect();

        $this->assertDatabaseHas('community_group_members', [
            'group_id' => $group->id,
            'user_id' => $target->id,
            'role' => 'member',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('community_group_invites', [
            'id' => $invite->id,
            'status' => 'accepted',
        ]);
    }

    /** @return array{0:Tenant,1:User} */
    private function tenantFixture(string $role = 'member'): array
    {
        $tenant = Tenant::create([
            'name' => 'Community Runtime Club',
            'slug' => 'community-runtime-club',
            'type' => Tenant::TYPE_CLUB,
            'status' => 'active',
            'plan' => 'starter',
            'settings' => ['marketplace_enabled' => true, 'groups_enabled' => true],
        ]);
        TenantDomain::create([
            'tenant_id' => $tenant->id,
            'domain' => 'club.platform.test',
            'type' => TenantDomain::TYPE_PLATFORM_SUBDOMAIN,
            'is_primary' => true,
            'status' => TenantDomain::STATUS_ACTIVE,
            'verified_at' => now(),
            'ssl_status' => 'active',
            'dns_status' => 'active',
        ]);
        $user = $this->newUser($role.'-runtime');
        $tenant->users()->attach($user->id, ['role' => $role, 'status' => 'active']);

        return [$tenant, $user];
    }

    private function member(Tenant $tenant, string $name): User
    {
        $user = $this->newUser($name);
        $tenant->users()->attach($user->id, ['role' => 'member', 'status' => 'active']);
        return $user;
    }

    private function newUser(string $name): User
    {
        return User::create([
            'name' => $name,
            'username' => $name,
            'display_name' => $name,
            'email' => $name.'@example.test',
            'password' => 'Password12345',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'status' => 'active',
            'is_platform_admin' => false,
            'adult_confirmed_at' => now(),
            'terms_accepted_at' => now(),
            'privacy_accepted_at' => now(),
            'privacy_version' => '1.0',
        ]);
    }
}
