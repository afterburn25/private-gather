<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\Profile;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LifestyleTicketEligibilityTest extends TestCase
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

    public function test_couple_admission_rejects_individual_profile_and_accepts_couple_profile(): void
    {
        [$tenant, $domain] = $this->createClub('couple-admission.test');
        $event = $this->createEvent($tenant);
        $ticket = $this->createTicket($event, profileEligibility: 'couple');
        $individual = $this->createUserWithProfile('individual@example.test', 'individual');
        $couple = $this->createUserWithProfile('couple@example.test', 'couple');

        $this->actingAs($individual)
            ->post('http://'.$domain.'/events/'.$event->id.'/tickets/'.$ticket->id, ['quantity' => 1])
            ->assertForbidden();

        $this->assertDatabaseMissing('orders', ['user_id' => $individual->id]);

        $this->actingAs($couple)
            ->post('http://'.$domain.'/events/'.$event->id.'/tickets/'.$ticket->id, ['quantity' => 1])
            ->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'tenant_id' => $tenant->id,
            'event_id' => $event->id,
            'user_id' => $couple->id,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('tickets', ['user_id' => $couple->id, 'status' => 'valid']);
    }

    public function test_member_only_ticket_requires_active_membership_in_that_specific_club(): void
    {
        [$tenant, $domain] = $this->createClub('member-ticket.test');
        $event = $this->createEvent($tenant);
        $ticket = $this->createTicket($event, membershipRequired: true);
        $member = $this->createUserWithProfile('member@example.test', 'individual');

        $this->actingAs($member)
            ->post('http://'.$domain.'/events/'.$event->id.'/tickets/'.$ticket->id, ['quantity' => 1])
            ->assertForbidden();

        $tenant->users()->attach($member->id, ['role' => 'member', 'status' => 'active']);

        $this->actingAs($member)
            ->post('http://'.$domain.'/events/'.$event->id.'/tickets/'.$ticket->id, ['quantity' => 1])
            ->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'tenant_id' => $tenant->id,
            'user_id' => $member->id,
            'status' => 'completed',
        ]);
    }

    public function test_approval_required_ticket_cannot_be_bought_until_event_attendance_is_approved(): void
    {
        [$tenant, $domain] = $this->createClub('approval-ticket.test');
        $event = $this->createEvent($tenant);
        $ticket = $this->createTicket($event, approvalRequired: true);
        $guest = $this->createUserWithProfile('approved-guest@example.test', 'couple');

        $this->actingAs($guest)
            ->post('http://'.$domain.'/events/'.$event->id.'/tickets/'.$ticket->id, ['quantity' => 1])
            ->assertForbidden();

        EventRsvp::create([
            'event_id' => $event->id,
            'user_id' => $guest->id,
            'status' => 'approved',
            'guest_count' => 2,
            'approved_at' => now(),
        ]);

        $this->actingAs($guest)
            ->post('http://'.$domain.'/events/'.$event->id.'/tickets/'.$ticket->id, ['quantity' => 1])
            ->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'tenant_id' => $tenant->id,
            'user_id' => $guest->id,
            'status' => 'completed',
        ]);
    }

    private function createClub(string $domain): array
    {
        $tenant = Tenant::create([
            'name' => 'Lifestyle Club',
            'slug' => 'club-'.Str::lower(Str::random(8)),
            'type' => Tenant::TYPE_CLUB,
            'status' => 'active',
            'plan' => 'starter',
            'settings' => ['marketplace_enabled' => true, 'market' => 'adult_lifestyle'],
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

    private function createEvent(Tenant $tenant): Event
    {
        return Event::create([
            'tenant_id' => $tenant->id,
            'title' => 'Private Lifestyle Social',
            'slug' => 'event-'.Str::lower(Str::random(8)),
            'visibility' => 'public',
            'rsvp_mode' => 'approval',
            'status' => 'published',
            'starts_at' => now()->addWeek(),
            'timezone' => 'America/Chicago',
            'capacity' => 100,
            'waitlist_enabled' => true,
            'exact_address_visibility' => 'approved_attendees',
        ]);
    }

    private function createTicket(
        Event $event,
        string $profileEligibility = 'any',
        bool $membershipRequired = false,
        bool $approvalRequired = false,
    ): TicketType {
        return TicketType::create([
            'event_id' => $event->id,
            'name' => 'Lifestyle Admission',
            'description' => 'Test admission rule',
            'profile_eligibility' => $profileEligibility,
            'membership_required' => $membershipRequired,
            'approval_required' => $approvalRequired,
            'price_cents' => 0,
            'currency' => 'USD',
            'quantity' => 50,
            'max_per_order' => 4,
            'active' => true,
        ]);
    }

    private function createUserWithProfile(string $email, string $profileType): User
    {
        $user = User::create([
            'name' => 'Lifestyle Guest',
            'display_name' => 'Lifestyle Guest',
            'email' => $email,
            'password' => 'Password123',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'status' => 'active',
            'adult_confirmed_at' => now(),
            'terms_accepted_at' => now(),
            'privacy_accepted_at' => now(),
            'privacy_version' => '1.0',
        ]);

        Profile::create([
            'user_id' => $user->id,
            'profile_type' => $profileType,
            'discoverable' => true,
        ]);

        return $user->fresh('profile');
    }
}
