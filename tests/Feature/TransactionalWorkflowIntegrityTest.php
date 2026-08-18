<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TicketType;
use App\Models\User;
use App\Services\CustomDomainService;
use App\Services\TicketIssuer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransactionalWorkflowIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancelled_rsvp_can_be_submitted_again_without_unique_key_failure(): void
    {
        $tenant = $this->createTenant();
        $user = $this->createUser('rsvp@example.test');
        $event = $this->createEvent($tenant, capacity: 10);
        EventRsvp::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'status' => 'cancelled',
            'guest_count' => 1,
        ]);

        $this->actingAs($user)
            ->post('/events/'.$event->id.'/rsvp', ['guest_count' => 2])
            ->assertRedirect();

        $this->assertDatabaseCount('event_rsvps', 1);
        $this->assertDatabaseHas('event_rsvps', [
            'event_id' => $event->id,
            'user_id' => $user->id,
            'status' => 'approved',
            'guest_count' => 2,
        ]);
    }

    public function test_organizer_cannot_approve_rsvp_beyond_event_capacity(): void
    {
        [$tenant, $domain] = $this->createTenantWithDomain('approval.test');
        $owner = $this->createUser('approval-owner@example.test');
        $fullGuest = $this->createUser('full@example.test');
        $pendingGuest = $this->createUser('pending@example.test');
        $tenant->users()->attach($owner->id, ['role' => 'owner', 'status' => 'active']);
        $event = $this->createEvent($tenant, capacity: 2, rsvpMode: 'approval');
        EventRsvp::create(['event_id' => $event->id, 'user_id' => $fullGuest->id, 'status' => 'approved', 'guest_count' => 2, 'approved_at' => now()]);
        $pending = EventRsvp::create(['event_id' => $event->id, 'user_id' => $pendingGuest->id, 'status' => 'pending', 'guest_count' => 1]);

        $this->actingAs($owner)
            ->patch('http://'.$domain.'/manage/events/'.$event->id.'/rsvps/'.$pending->id, ['status' => 'approved'])
            ->assertStatus(422);

        $this->assertDatabaseHas('event_rsvps', ['id' => $pending->id, 'status' => 'pending']);
    }

    public function test_mark_paid_rechecks_inventory_and_refuses_oversell(): void
    {
        [$tenant, $domain] = $this->createTenantWithDomain('orders.test');
        $owner = $this->createUser('orders-owner@example.test');
        $buyerA = $this->createUser('buyer-a@example.test');
        $buyerB = $this->createUser('buyer-b@example.test');
        $tenant->users()->attach($owner->id, ['role' => 'owner', 'status' => 'active']);
        $event = $this->createEvent($tenant, capacity: 100);
        $type = TicketType::create([
            'event_id' => $event->id,
            'name' => 'Limited',
            'price_cents' => 2500,
            'currency' => 'USD',
            'quantity' => 2,
            'max_per_order' => 10,
            'active' => true,
        ]);
        $paid = $this->createOrder($tenant, $event, $buyerA, 'paid');
        OrderItem::create(['order_id' => $paid->id, 'ticket_type_id' => $type->id, 'quantity' => 2, 'unit_price_cents' => 2500, 'total_cents' => 5000]);
        $pending = $this->createOrder($tenant, $event, $buyerB, 'pending');
        OrderItem::create(['order_id' => $pending->id, 'ticket_type_id' => $type->id, 'quantity' => 1, 'unit_price_cents' => 2500, 'total_cents' => 2500]);

        $this->actingAs($owner)
            ->post('http://'.$domain.'/manage/orders/'.$pending->id.'/paid')
            ->assertStatus(422);

        $this->assertDatabaseHas('orders', ['id' => $pending->id, 'status' => 'pending']);
        $this->assertDatabaseMissing('tickets', ['user_id' => $buyerB->id]);
    }

    public function test_ticket_issuer_is_idempotent_for_retries(): void
    {
        $tenant = $this->createTenant();
        $buyer = $this->createUser('tickets@example.test');
        $event = $this->createEvent($tenant, capacity: 100);
        $type = TicketType::create([
            'event_id' => $event->id,
            'name' => 'General',
            'price_cents' => 0,
            'currency' => 'USD',
            'quantity' => 10,
            'max_per_order' => 10,
            'active' => true,
        ]);
        $order = $this->createOrder($tenant, $event, $buyer, 'paid');
        OrderItem::create(['order_id' => $order->id, 'ticket_type_id' => $type->id, 'quantity' => 2, 'unit_price_cents' => 0, 'total_cents' => 0]);
        $issuer = app(TicketIssuer::class);

        $this->assertSame(2, $issuer->issue($order));
        $this->assertSame(0, $issuer->issue($order));
        $this->assertDatabaseCount('tickets', 2);
    }

    public function test_domain_verification_instruction_uses_private_gather_prefix(): void
    {
        $tenant = $this->createTenant();
        $domain = app(CustomDomainService::class)->request($tenant, 'club.example.test');
        $instructions = app(CustomDomainService::class)->dnsInstructions($domain);

        $this->assertSame('_privategather-verification.club.example.test', $instructions['verification_record']);
    }

    private function createUser(string $email): User
    {
        return User::create([
            'name' => 'Test Member',
            'display_name' => 'Test Member',
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

    private function createTenant(): Tenant
    {
        return Tenant::create([
            'name' => 'Test Organization',
            'slug' => 'tenant-'.Str::lower(Str::random(8)),
            'type' => 'club',
            'status' => 'active',
            'plan' => 'starter',
            'settings' => ['marketplace_enabled' => true],
        ]);
    }

    private function createTenantWithDomain(string $domain): array
    {
        $tenant = $this->createTenant();
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

    private function createEvent(Tenant $tenant, int $capacity, string $rsvpMode = 'instant'): Event
    {
        return Event::create([
            'tenant_id' => $tenant->id,
            'title' => 'Test Event',
            'slug' => 'event-'.Str::lower(Str::random(8)),
            'visibility' => 'public',
            'rsvp_mode' => $rsvpMode,
            'status' => 'published',
            'starts_at' => now()->addWeek(),
            'timezone' => 'America/Chicago',
            'capacity' => $capacity,
            'waitlist_enabled' => true,
            'exact_address_visibility' => 'approved_attendees',
        ]);
    }

    private function createOrder(Tenant $tenant, Event $event, User $buyer, string $status): Order
    {
        return Order::create([
            'public_id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'event_id' => $event->id,
            'user_id' => $buyer->id,
            'status' => $status,
            'currency' => 'USD',
            'subtotal_cents' => 0,
            'discount_cents' => 0,
            'fee_cents' => 0,
            'total_cents' => 0,
        ]);
    }
}
