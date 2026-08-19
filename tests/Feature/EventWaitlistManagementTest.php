<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EventWaitlistManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_organizer_can_see_and_promote_waitlist_after_capacity_reopens(): void
    {
        [$tenant, $domain] = $this->createTenant('waitlist.test');
        $owner = $this->createUser('waitlist-owner@example.test');
        $approved = $this->createUser('approved@example.test');
        $waiting = $this->createUser('waiting@example.test');
        $tenant->users()->attach($owner->id, ['role' => 'owner', 'status' => 'active']);
        $event = $this->createEvent($tenant, 'Managed Waitlist Event', 'public', 2);

        $approvedRsvp = EventRsvp::create([
            'event_id' => $event->id,
            'user_id' => $approved->id,
            'status' => 'approved',
            'guest_count' => 2,
            'answers' => [],
            'approved_at' => now(),
        ]);
        $waitlistId = $this->addWaitlist($event, $waiting, 1, 1);

        $this->actingAs($owner)
            ->get('http://'.$domain.'/manage/events/'.$event->id.'/waitlist')
            ->assertOk()
            ->assertSee('waiting@example.test')
            ->assertSee('0')
            ->assertSee('Seats available');

        $this->actingAs($owner)
            ->patch('http://'.$domain.'/manage/events/'.$event->id.'/waitlist/'.$waitlistId.'/promote')
            ->assertStatus(422);

        $this->assertDatabaseHas('event_waitlist', ['id' => $waitlistId]);
        $this->assertDatabaseMissing('event_rsvps', [
            'event_id' => $event->id,
            'user_id' => $waiting->id,
        ]);

        $this->actingAs($owner)
            ->patch('http://'.$domain.'/manage/events/'.$event->id.'/rsvps/'.$approvedRsvp->id, ['status' => 'cancelled'])
            ->assertRedirect();

        $this->actingAs($owner)
            ->patch('http://'.$domain.'/manage/events/'.$event->id.'/waitlist/'.$waitlistId.'/promote')
            ->assertRedirect();

        $this->assertDatabaseMissing('event_waitlist', ['id' => $waitlistId]);
        $this->assertDatabaseHas('event_rsvps', [
            'event_id' => $event->id,
            'user_id' => $waiting->id,
            'status' => 'approved',
            'guest_count' => 1,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'action' => 'event.waitlist.promoted',
        ]);
    }

    public function test_waitlist_promotion_is_tenant_scoped(): void
    {
        [$tenantA, $domainA] = $this->createTenant('waitlist-a.test');
        [$tenantB] = $this->createTenant('waitlist-b.test');
        $owner = $this->createUser('waitlist-scope-owner@example.test');
        $waiting = $this->createUser('foreign-waiting@example.test');
        $tenantA->users()->attach($owner->id, ['role' => 'owner', 'status' => 'active']);
        $eventA = $this->createEvent($tenantA, 'Tenant A Event', 'public', 10);
        $eventB = $this->createEvent($tenantB, 'Tenant B Event', 'public', 10);
        $foreignWaitlistId = $this->addWaitlist($eventB, $waiting, 1, 1);

        $this->actingAs($owner)
            ->patch('http://'.$domainA.'/manage/events/'.$eventA->id.'/waitlist/'.$foreignWaitlistId.'/promote')
            ->assertNotFound();

        $this->assertDatabaseHas('event_waitlist', ['id' => $foreignWaitlistId]);
        $this->assertDatabaseMissing('event_rsvps', [
            'event_id' => $eventA->id,
            'user_id' => $waiting->id,
        ]);
    }

    public function test_members_event_waitlist_does_not_promote_suspended_membership(): void
    {
        [$tenant, $domain] = $this->createTenant('members-waitlist.test');
        $owner = $this->createUser('members-waitlist-owner@example.test');
        $waiting = $this->createUser('suspended-waiting@example.test');
        $tenant->users()->attach($owner->id, ['role' => 'owner', 'status' => 'active']);
        $tenant->users()->attach($waiting->id, ['role' => 'member', 'status' => 'suspended']);
        $event = $this->createEvent($tenant, 'Members Waitlist Event', 'members', 10);
        $waitlistId = $this->addWaitlist($event, $waiting, 1, 1);

        $this->actingAs($owner)
            ->patch('http://'.$domain.'/manage/events/'.$event->id.'/waitlist/'.$waitlistId.'/promote')
            ->assertStatus(422);

        $this->assertDatabaseHas('event_waitlist', ['id' => $waitlistId]);
        $this->assertDatabaseMissing('event_rsvps', [
            'event_id' => $event->id,
            'user_id' => $waiting->id,
        ]);
    }

    private function createTenant(string $domain): array
    {
        $tenant = Tenant::create([
            'name' => 'Waitlist Club',
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
            'name' => 'Waitlist User',
            'display_name' => 'Waitlist User',
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

    private function createEvent(Tenant $tenant, string $title, string $visibility, ?int $capacity): Event
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
            'capacity' => $capacity,
            'waitlist_enabled' => true,
            'exact_address_visibility' => 'approved_attendees',
        ]);
    }

    private function addWaitlist(Event $event, User $user, int $guestCount, int $position): int
    {
        return (int) DB::table('event_waitlist')->insertGetId([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'guest_count' => $guestCount,
            'position' => $position,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
