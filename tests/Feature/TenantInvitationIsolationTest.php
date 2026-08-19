<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventInvitation;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantInvitationIsolationTest extends TestCase
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

    public function test_event_invitation_cannot_be_viewed_or_accepted_through_foreign_tenant_domain(): void
    {
        [$tenantA, $domainA] = $this->createTenant('event-invite-a.test');
        [$tenantB] = $this->createTenant('event-invite-b.test');
        $member = $this->createUser('event-invite-member@example.test');
        $tenantA->users()->attach($member->id, ['role' => 'member', 'status' => 'active']);
        $tenantB->users()->attach($member->id, ['role' => 'member', 'status' => 'active']);
        $eventB = $this->createEvent($tenantB, 'Tenant B Invitation Event');
        $token = str_repeat('a', 64);

        EventInvitation::create([
            'event_id' => $eventB->id,
            'user_id' => $member->id,
            'email' => $member->email,
            'token' => 'sha256:'.hash('sha256', $token),
            'status' => 'pending',
            'max_guests' => 2,
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($member)
            ->get('http://'.$domainA.'/event-invite/'.$token)
            ->assertNotFound();

        $this->actingAs($member)
            ->post('http://'.$domainA.'/event-invite/'.$token, ['guest_count' => 1])
            ->assertNotFound();

        $this->assertDatabaseMissing('event_rsvps', [
            'event_id' => $eventB->id,
            'user_id' => $member->id,
        ]);
        $this->assertDatabaseHas('event_invitations', [
            'event_id' => $eventB->id,
            'status' => 'pending',
        ]);

        $this->actingAs($member)
            ->get('http://platform.test/event-invite/'.$token)
            ->assertOk()
            ->assertSee('Tenant B Invitation Event');

        $this->actingAs($member)
            ->post('http://platform.test/event-invite/'.$token, ['guest_count' => 1])
            ->assertRedirect();

        $this->assertDatabaseHas('event_rsvps', [
            'event_id' => $eventB->id,
            'user_id' => $member->id,
            'status' => 'approved',
        ]);
        $this->assertDatabaseHas('event_invitations', [
            'event_id' => $eventB->id,
            'status' => 'accepted',
            'user_id' => $member->id,
        ]);
    }

    public function test_staff_invitation_cannot_be_accepted_through_foreign_tenant_domain(): void
    {
        [$tenantA, $domainA] = $this->createTenant('staff-invite-a.test');
        [$tenantB, $domainB] = $this->createTenant('staff-invite-b.test');
        $invitee = $this->createUser('staff-invite-member@example.test');
        $tenantA->users()->attach($invitee->id, ['role' => 'member', 'status' => 'active']);
        $token = str_repeat('b', 64);

        DB::table('tenant_invitations')->insert([
            'tenant_id' => $tenantB->id,
            'email' => $invitee->email,
            'role' => 'staff',
            'token' => hash('sha256', $token),
            'expires_at' => now()->addDay(),
            'accepted_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($invitee)
            ->get('http://'.$domainA.'/staff-invite/'.$token)
            ->assertNotFound();

        $this->assertDatabaseMissing('tenant_users', [
            'tenant_id' => $tenantB->id,
            'user_id' => $invitee->id,
        ]);
        $this->assertDatabaseHas('tenant_invitations', [
            'tenant_id' => $tenantB->id,
            'accepted_at' => null,
        ]);

        $this->actingAs($invitee)
            ->get('http://platform.test/staff-invite/'.$token)
            ->assertRedirect('https://'.$domainB.'/manage');

        $this->assertDatabaseHas('tenant_users', [
            'tenant_id' => $tenantB->id,
            'user_id' => $invitee->id,
            'role' => 'staff',
            'status' => 'active',
        ]);
    }

    private function createUser(string $email): User
    {
        return User::create([
            'name' => 'Invitation Test User',
            'display_name' => 'Invitation Test User',
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

    private function createTenant(string $domain): array
    {
        $tenant = Tenant::create([
            'name' => 'Invitation '.Str::before($domain, '.'),
            'slug' => str_replace('.', '-', $domain),
            'type' => 'club',
            'status' => 'active',
            'plan' => 'starter',
            'settings' => ['marketplace_enabled' => true],
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

        return [$tenant, $domain];
    }

    private function createEvent(Tenant $tenant, string $title): Event
    {
        return Event::create([
            'tenant_id' => $tenant->id,
            'title' => $title,
            'slug' => Str::slug($title),
            'visibility' => 'invite_only',
            'rsvp_mode' => 'instant',
            'status' => 'published',
            'starts_at' => now()->addWeek(),
            'timezone' => 'America/Chicago',
            'capacity' => 50,
            'waitlist_enabled' => true,
            'exact_address_visibility' => 'approved_attendees',
        ]);
    }
}
