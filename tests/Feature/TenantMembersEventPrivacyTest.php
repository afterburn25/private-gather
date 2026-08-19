<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventInvitation;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantMembersEventPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_logged_in_nonmember_cannot_discover_open_rsvp_or_checkout_members_event(): void
    {
        [$tenant, $domain] = $this->createTenant('members-private.test');
        $outsider = $this->createUser('outsider@example.test');
        $public = $this->createEvent($tenant, 'Public Tenant Event', 'public');
        $members = $this->createEvent($tenant, 'Tenant Members Event', 'members');
        $ticketType = $this->createTicketType($members);

        $this->actingAs($outsider)
            ->get('http://'.$domain.'/events')
            ->assertOk()
            ->assertSee($public->title)
            ->assertDontSee($members->title);

        $home = $this->actingAs($outsider)
            ->get('http://'.$domain.'/')
            ->assertOk();
        $homeEventTitles = $home->viewData('events')->pluck('title')->all();
        $this->assertContains($public->title, $homeEventTitles);
        $this->assertNotContains($members->title, $homeEventTitles);

        $this->actingAs($outsider)
            ->get('http://'.$domain.'/events/'.$members->id)
            ->assertForbidden();

        $this->actingAs($outsider)
            ->post('http://'.$domain.'/events/'.$members->id.'/rsvp', ['guest_count' => 1])
            ->assertForbidden();

        $this->actingAs($outsider)
            ->post('http://'.$domain.'/events/'.$members->id.'/tickets/'.$ticketType->id, ['quantity' => 1])
            ->assertForbidden();

        $this->assertDatabaseMissing('event_rsvps', [
            'event_id' => $members->id,
            'user_id' => $outsider->id,
        ]);
        $this->assertDatabaseMissing('orders', [
            'event_id' => $members->id,
            'user_id' => $outsider->id,
        ]);
    }

    public function test_legacy_members_event_invitation_cannot_grant_nonmember_access(): void
    {
        [$tenant, $domain] = $this->createTenant('members-invite-private.test');
        $outsider = $this->createUser('members-invite-outsider@example.test');
        $event = $this->createEvent($tenant, 'Legacy Members Invitation Event', 'members');
        $token = str_repeat('d', 64);

        EventInvitation::create([
            'event_id' => $event->id,
            'user_id' => $outsider->id,
            'email' => $outsider->email,
            'token' => 'sha256:'.hash('sha256', $token),
            'status' => 'pending',
            'max_guests' => 1,
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($outsider)
            ->get('http://'.$domain.'/event-invite/'.$token)
            ->assertForbidden();

        $this->actingAs($outsider)
            ->post('http://'.$domain.'/event-invite/'.$token, ['guest_count' => 1])
            ->assertForbidden();

        $this->assertDatabaseMissing('event_rsvps', [
            'event_id' => $event->id,
            'user_id' => $outsider->id,
        ]);
        $this->assertDatabaseHas('event_invitations', [
            'event_id' => $event->id,
            'user_id' => $outsider->id,
            'status' => 'pending',
        ]);
    }

    public function test_active_tenant_member_can_discover_open_rsvp_and_checkout_members_event(): void
    {
        [$tenant, $domain] = $this->createTenant('members-visible.test');
        $member = $this->createUser('member@example.test');
        $tenant->users()->attach($member->id, ['role' => 'member', 'status' => 'active']);
        $event = $this->createEvent($tenant, 'Active Members Event', 'members');
        $ticketType = $this->createTicketType($event);

        $this->actingAs($member)
            ->get('http://'.$domain.'/events')
            ->assertOk()
            ->assertSee($event->title);

        $this->actingAs($member)
            ->get('http://'.$domain.'/events/'.$event->id)
            ->assertOk()
            ->assertSee($event->title);

        $this->actingAs($member)
            ->post('http://'.$domain.'/events/'.$event->id.'/rsvp', ['guest_count' => 1])
            ->assertRedirect();

        $this->assertDatabaseHas('event_rsvps', [
            'event_id' => $event->id,
            'user_id' => $member->id,
            'status' => 'approved',
        ]);

        $this->actingAs($member)
            ->post('http://'.$domain.'/events/'.$event->id.'/tickets/'.$ticketType->id, ['quantity' => 1])
            ->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'event_id' => $event->id,
            'user_id' => $member->id,
            'status' => 'completed',
        ]);
    }

    public function test_inactive_tenant_membership_does_not_unlock_members_event(): void
    {
        [$tenant, $domain] = $this->createTenant('members-inactive.test');
        $member = $this->createUser('inactive-member@example.test');
        $tenant->users()->attach($member->id, ['role' => 'member', 'status' => 'suspended']);
        $event = $this->createEvent($tenant, 'Suspended Members Event', 'members');
        $ticketType = $this->createTicketType($event);

        $this->actingAs($member)
            ->get('http://'.$domain.'/events')
            ->assertOk()
            ->assertDontSee($event->title);

        $this->actingAs($member)
            ->get('http://'.$domain.'/events/'.$event->id)
            ->assertForbidden();

        $this->actingAs($member)
            ->post('http://'.$domain.'/events/'.$event->id.'/rsvp', ['guest_count' => 1])
            ->assertForbidden();

        $this->actingAs($member)
            ->post('http://'.$domain.'/events/'.$event->id.'/tickets/'.$ticketType->id, ['quantity' => 1])
            ->assertForbidden();
    }

    private function createTenant(string $domain): array
    {
        $tenant = Tenant::create([
            'name' => 'Members Privacy Club',
            'slug' => str_replace('.', '-', $domain),
            'type' => Tenant::TYPE_CLUB,
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

    private function createUser(string $email): User
    {
        return User::create([
            'name' => 'Members Privacy User',
            'display_name' => 'Members Privacy User',
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

    private function createEvent(Tenant $tenant, string $title, string $visibility): Event
    {
        return Event::create([
            'tenant_id' => $tenant->id,
            'title' => $title,
            'slug' => strtolower(str_replace(' ', '-', $title)),
            'visibility' => $visibility,
            'rsvp_mode' => 'instant',
            'status' => 'published',
            'starts_at' => now()->addWeek(),
            'timezone' => 'America/Chicago',
            'capacity' => 50,
            'waitlist_enabled' => true,
            'exact_address_visibility' => 'approved_attendees',
        ]);
    }

    private function createTicketType(Event $event): TicketType
    {
        return TicketType::create([
            'event_id' => $event->id,
            'name' => 'Members Admission',
            'price_cents' => 0,
            'currency' => 'USD',
            'quantity' => 20,
            'max_per_order' => 2,
            'active' => true,
        ]);
    }
}
