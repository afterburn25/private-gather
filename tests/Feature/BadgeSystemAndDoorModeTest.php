<?php

namespace Tests\Feature;

use App\Models\Badge;
use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\Profile;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use App\Models\UserBadge;
use App\Services\BadgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BadgeSystemAndDoorModeTest extends TestCase
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

    public function test_global_and_club_badges_have_separate_authority_and_provenance(): void
    {
        $admin = $this->createUser('admin@example.test', true);
        [$clubA, $domainA, $ownerA] = $this->createClub('velvet.test');
        [$clubB, $domainB, $ownerB] = $this->createClub('other.test');
        $member = $this->createUser('member@example.test');
        $clubA->users()->attach($member->id, ['role' => 'member', 'status' => 'active']);

        $this->actingAs($admin)->post('http://platform.test/admin/badges', [
            'name' => 'Trusted Member',
            'slug' => 'trusted-member',
            'description' => 'Private Gather platform recognition.',
            'icon' => 'PG',
            'badge_color' => '#7d3b69',
            'text_color' => '#ffffff',
            'category' => 'reputation',
            'visibility' => 'members',
            'issuance_type' => 'manual',
            'is_system_reserved' => '1',
        ])->assertRedirect();

        $global = Badge::where('slug', 'trusted-member')->firstOrFail();
        $this->assertTrue($global->isGlobal());

        $this->actingAs($admin)->post('http://platform.test/admin/badges/'.$global->id.'/assign', [
            'email' => $member->email,
        ])->assertRedirect();

        $this->actingAs($ownerA)->post('http://'.$domainA.'/manage/badges', [
            'name' => 'Velvet VIP',
            'slug' => 'velvet-vip',
            'description' => 'VIP member of Velvet.',
            'icon' => '★',
            'badge_color' => '#7d3b69',
            'text_color' => '#ffffff',
            'category' => 'membership',
            'visibility' => 'tenant_members',
            'issuance_type' => 'manual',
        ])->assertRedirect();

        $clubBadge = Badge::where('tenant_id', $clubA->id)->where('slug', 'velvet-vip')->firstOrFail();
        $this->actingAs($ownerA)->post('http://'.$domainA.'/manage/badges/'.$clubBadge->id.'/assign', [
            'email' => $member->email,
        ])->assertRedirect();

        $assignment = UserBadge::where('badge_id', $clubBadge->id)->where('user_id', $member->id)->firstOrFail();
        $this->assertSame($clubA->id, $assignment->tenant_id);
        $this->assertSame($ownerA->id, $assignment->issued_by);

        $this->actingAs($ownerB)->delete('http://'.$domainB.'/manage/badges/'.$clubBadge->id.'/assignments/'.$assignment->id, [
            'reason' => 'Cross-tenant attempt',
        ])->assertNotFound();
        $this->assertNull($assignment->fresh()->revoked_at);

        $this->actingAs($ownerA)->from('http://'.$domainA.'/manage/badges')->post('http://'.$domainA.'/manage/badges', [
            'name' => 'Identity Verified',
            'badge_color' => '#7d3b69',
            'text_color' => '#ffffff',
            'category' => 'custom',
            'visibility' => 'tenant_members',
            'issuance_type' => 'manual',
        ])->assertRedirect('http://'.$domainA.'/manage/badges')->assertSessionHasErrors('name');

        $this->actingAs($member)->get('http://platform.test/badges')
            ->assertOk()
            ->assertSee('Trusted Member')
            ->assertSee('Velvet VIP')
            ->assertSee('issued by Private Gather')
            ->assertSee($clubA->name);

        $this->assertSame($clubB->id, $clubB->fresh()->id);
    }

    public function test_membership_and_event_badges_sync_into_capacity_safe_door_mode(): void
    {
        [$club, $domain, $owner] = $this->createClub('door.test');
        $member = $this->createUser('door-member@example.test');
        $club->users()->attach($member->id, ['role' => 'member', 'status' => 'active']);
        Profile::create([
            'user_id' => $member->id,
            'profile_type' => 'couple',
            'headline' => 'Test couple',
            'visibility' => [],
            'discoverable' => true,
        ]);

        $membershipBadge = Badge::create([
            'tenant_id' => $club->id,
            'scope' => Badge::SCOPE_TENANT,
            'name' => 'Active Club Member',
            'slug' => 'active-club-member',
            'category' => 'membership',
            'visibility' => 'staff',
            'issuance_type' => Badge::ISSUE_MEMBERSHIP,
            'criteria' => [],
            'is_active' => true,
            'created_by' => $owner->id,
        ]);
        $eventBadge = Badge::create([
            'tenant_id' => $club->id,
            'scope' => Badge::SCOPE_TENANT,
            'name' => 'First Event',
            'slug' => 'first-event',
            'category' => 'achievement',
            'visibility' => 'staff',
            'issuance_type' => Badge::ISSUE_AUTOMATIC,
            'criteria' => ['event_count' => 1],
            'is_active' => true,
            'created_by' => $owner->id,
        ]);

        $event = Event::create([
            'tenant_id' => $club->id,
            'title' => 'Private Social',
            'slug' => 'private-social',
            'visibility' => 'members',
            'rsvp_mode' => 'approval',
            'status' => 'published',
            'starts_at' => now()->addHour(),
            'timezone' => 'America/Chicago',
            'capacity' => 1,
            'public_location_label' => 'Metro Area',
            'exact_address_visibility' => 'approved_attendees',
        ]);
        EventRsvp::create([
            'event_id' => $event->id,
            'user_id' => $member->id,
            'status' => 'approved',
            'guest_count' => 1,
            'answers' => [],
            'approved_at' => now(),
        ]);

        app(BadgeService::class)->syncForUser($member, $club->id);
        $this->assertDatabaseHas('user_badges', ['badge_id' => $membershipBadge->id, 'user_id' => $member->id, 'tenant_id' => $club->id]);
        $this->assertDatabaseMissing('user_badges', ['badge_id' => $eventBadge->id, 'user_id' => $member->id]);

        $this->actingAs($owner)->get('http://'.$domain.'/manage/events/'.$event->id.'/check-in')
            ->assertOk()
            ->assertSee('DOOR MODE')
            ->assertSee('Active Club Member')
            ->assertSee('Couple profile');

        $this->actingAs($owner)->post('http://'.$domain.'/manage/events/'.$event->id.'/check-in/manual', [
            'user_id' => $member->id,
            'guest_count' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('user_badges', ['badge_id' => $eventBadge->id, 'user_id' => $member->id, 'tenant_id' => $club->id]);

        $this->actingAs($owner)->get('http://'.$domain.'/manage/events/'.$event->id.'/check-in')
            ->assertOk()
            ->assertSee('First Event')
            ->assertSee('Space Remaining')
            ->assertSee('0');

        $this->actingAs($owner)->post('http://'.$domain.'/manage/events/'.$event->id.'/check-in/manual', [
            'guest_name' => 'Capacity Overflow',
            'guest_count' => 1,
        ])->assertStatus(422);

        $this->assertDatabaseCount('event_checkins', 1);
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

    private function createUser(string $email, bool $admin = false): User
    {
        return User::create([
            'name' => 'Lifestyle Member',
            'display_name' => 'Lifestyle Member',
            'email' => $email,
            'password' => 'Password123',
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
