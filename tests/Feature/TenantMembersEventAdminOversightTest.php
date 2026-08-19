<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantMembersEventAdminOversightTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_view_but_cannot_transact_without_tenant_membership(): void
    {
        $tenant = Tenant::create([
            'name' => 'Oversight Club',
            'slug' => 'oversight-club',
            'type' => Tenant::TYPE_CLUB,
            'status' => 'active',
            'plan' => 'starter',
            'settings' => ['marketplace_enabled' => true],
        ]);

        TenantDomain::create([
            'tenant_id' => $tenant->id,
            'domain' => 'oversight-members.test',
            'type' => TenantDomain::TYPE_CUSTOM_DOMAIN,
            'is_primary' => true,
            'status' => TenantDomain::STATUS_ACTIVE,
            'verified_at' => now(),
            'ssl_status' => 'active',
            'dns_status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Platform Admin',
            'display_name' => 'Platform Admin',
            'email' => 'oversight-admin@example.test',
            'password' => 'Password123',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'status' => 'active',
            'is_platform_admin' => true,
            'adult_confirmed_at' => now(),
            'terms_accepted_at' => now(),
            'privacy_accepted_at' => now(),
            'privacy_version' => '1.0',
        ]);

        $event = Event::create([
            'tenant_id' => $tenant->id,
            'title' => 'Members Oversight Event',
            'slug' => 'members-oversight-event',
            'visibility' => 'members',
            'rsvp_mode' => 'instant',
            'status' => 'published',
            'starts_at' => now()->addWeek(),
            'timezone' => 'America/Chicago',
            'capacity' => 50,
            'waitlist_enabled' => true,
            'exact_address_visibility' => 'approved_attendees',
        ]);

        $ticketType = TicketType::create([
            'event_id' => $event->id,
            'name' => 'Members Admission',
            'price_cents' => 0,
            'currency' => 'USD',
            'quantity' => 20,
            'max_per_order' => 2,
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->get('http://oversight-members.test/events/'.$event->id)
            ->assertOk()
            ->assertSee($event->title);

        $this->actingAs($admin)
            ->post('http://oversight-members.test/events/'.$event->id.'/rsvp', ['guest_count' => 1])
            ->assertForbidden();

        $this->actingAs($admin)
            ->post('http://oversight-members.test/events/'.$event->id.'/tickets/'.$ticketType->id, ['quantity' => 1])
            ->assertForbidden();

        $this->assertDatabaseMissing('event_rsvps', [
            'event_id' => $event->id,
            'user_id' => $admin->id,
        ]);
        $this->assertDatabaseMissing('orders', [
            'event_id' => $event->id,
            'user_id' => $admin->id,
        ]);
    }
}
