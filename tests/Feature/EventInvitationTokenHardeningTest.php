<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EventInvitationTokenHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_event_invitation_stores_only_digest_and_displays_raw_link_once(): void
    {
        [$tenant, $domain] = $this->createTenant('event-invite-hash.test');
        $owner = $this->createUser('event-owner@example.test');
        $invitee = $this->createUser('event-invitee@example.test');
        $tenant->users()->attach($owner->id, ['role' => 'owner', 'status' => 'active']);
        $event = $this->createEvent($tenant);

        $response = $this->actingAs($owner)
            ->from('http://'.$domain.'/manage/events/'.$event->id.'/edit')
            ->post('http://'.$domain.'/manage/events/'.$event->id.'/invitations', [
                'email' => $invitee->email,
                'max_guests' => 2,
            ]);

        $response->assertRedirect('http://'.$domain.'/manage/events/'.$event->id.'/edit')
            ->assertSessionHas('event_invitation_url');

        $url = (string) session('event_invitation_url');
        $this->assertMatchesRegularExpression('#/event-invite/([a-f0-9]{64})$#', $url);
        preg_match('#/event-invite/([a-f0-9]{64})$#', $url, $matches);
        $rawToken = $matches[1];

        $stored = (string) DB::table('event_invitations')
            ->where('event_id', $event->id)
            ->value('token');

        $this->assertNotSame($rawToken, $stored);
        $this->assertSame('sha256:'.hash('sha256', $rawToken), $stored);

        $this->actingAs($owner)
            ->get('http://'.$domain.'/manage/events/'.$event->id.'/edit')
            ->assertOk()
            ->assertSee($rawToken)
            ->assertDontSee($stored);

        $this->actingAs($owner)
            ->get('http://'.$domain.'/manage/events/'.$event->id.'/edit')
            ->assertOk()
            ->assertDontSee($rawToken)
            ->assertDontSee($stored);

        $this->actingAs($invitee)
            ->get('/event-invite/'.$rawToken)
            ->assertOk()
            ->assertSee($event->title);

        $this->actingAs($invitee)
            ->post('/event-invite/'.$rawToken, ['guest_count' => 2])
            ->assertRedirect(route('events.show', $event->id));

        $this->assertDatabaseHas('event_rsvps', [
            'event_id' => $event->id,
            'user_id' => $invitee->id,
            'status' => 'approved',
            'guest_count' => 2,
        ]);
    }

    public function test_data_hardening_migration_converts_existing_plaintext_links_without_breaking_them(): void
    {
        [$tenant] = $this->createTenant('legacy-event-invite.test');
        $invitee = $this->createUser('legacy-event-invitee@example.test');
        $event = $this->createEvent($tenant);
        $rawToken = str_repeat('LegacyInviteToken1234567890', 3);
        $rawToken = substr($rawToken, 0, 64);

        DB::table('event_invitations')->insert([
            'event_id' => $event->id,
            'email' => $invitee->email,
            'token' => $rawToken,
            'status' => 'pending',
            'max_guests' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_08_18_021500_hash_existing_event_invitation_tokens.php');
        $migration->up();

        $this->assertDatabaseHas('event_invitations', [
            'event_id' => $event->id,
            'token' => 'sha256:'.hash('sha256', $rawToken),
        ]);

        $this->actingAs($invitee)
            ->get('/event-invite/'.$rawToken)
            ->assertOk()
            ->assertSee($event->title);
    }

    public function test_malformed_event_invitation_token_is_rejected(): void
    {
        $user = $this->createUser('malformed-invite@example.test');

        $this->actingAs($user)
            ->get('/event-invite/not-a-valid-token')
            ->assertNotFound();
    }

    private function createUser(string $email): User
    {
        return User::create([
            'name' => 'Test User',
            'display_name' => 'Test User',
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
            'name' => 'Invitation Test Organization',
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

    private function createEvent(Tenant $tenant): Event
    {
        return Event::create([
            'tenant_id' => $tenant->id,
            'title' => 'Private Invitation Event',
            'slug' => 'private-invitation-event',
            'visibility' => 'invite_only',
            'rsvp_mode' => 'invite_only',
            'status' => 'published',
            'starts_at' => now()->addWeek(),
            'timezone' => 'America/Chicago',
            'capacity' => 20,
            'waitlist_enabled' => true,
            'exact_address_visibility' => 'approved_attendees',
        ]);
    }
}
