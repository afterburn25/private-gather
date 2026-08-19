<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantActionIsolationTest extends TestCase
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

    public function test_tenant_domain_cannot_cancel_rsvp_for_foreign_tenant(): void
    {
        [$tenantA, $domainA] = $this->createTenant('isolation-a.test');
        [$tenantB] = $this->createTenant('isolation-b.test');
        $member = $this->createUser('rsvp-isolation@example.test');
        $tenantA->users()->attach($member->id, ['role' => 'member', 'status' => 'active']);
        $tenantB->users()->attach($member->id, ['role' => 'member', 'status' => 'active']);
        $eventB = $this->createEvent($tenantB, 'Foreign RSVP Event');

        EventRsvp::create([
            'event_id' => $eventB->id,
            'user_id' => $member->id,
            'status' => 'approved',
            'guest_count' => 1,
            'answers' => [],
            'approved_at' => now(),
        ]);

        $this->actingAs($member)
            ->delete('http://'.$domainA.'/events/'.$eventB->id.'/rsvp')
            ->assertNotFound();

        $this->assertDatabaseHas('event_rsvps', [
            'event_id' => $eventB->id,
            'user_id' => $member->id,
            'status' => 'approved',
        ]);
    }

    public function test_tenant_domain_cannot_submit_reports_for_foreign_tenant_targets(): void
    {
        [$tenantA, $domainA] = $this->createTenant('report-a.test');
        [$tenantB] = $this->createTenant('report-b.test');
        $reporter = $this->createUser('reporter@example.test');
        $target = $this->createUser('foreign-target@example.test');
        $tenantA->users()->attach($reporter->id, ['role' => 'member', 'status' => 'active']);
        $tenantB->users()->attach($reporter->id, ['role' => 'member', 'status' => 'active']);
        $tenantB->users()->attach($target->id, ['role' => 'member', 'status' => 'active']);
        $eventB = $this->createEvent($tenantB, 'Foreign Report Event');

        $conversation = Conversation::create([
            'tenant_id' => $tenantB->id,
            'type' => 'direct',
        ]);
        $conversation->participants()->attach($reporter->id, ['last_read_at' => now()]);
        $conversation->participants()->attach($target->id, ['last_read_at' => now()]);
        $message = $conversation->messages()->create([
            'user_id' => $target->id,
            'body' => 'foreign tenant message',
            'status' => 'sent',
        ]);

        foreach ([
            ['user', $target->id],
            ['event', $eventB->id],
            ['tenant', $tenantB->id],
            ['message', $message->id],
        ] as [$type, $id]) {
            $this->actingAs($reporter)
                ->post('http://'.$domainA.'/reports', [
                    'reportable_type' => $type,
                    'reportable_id' => $id,
                    'category' => 'safety',
                    'details' => 'must remain tenant scoped',
                ])
                ->assertNotFound();
        }

        $this->assertDatabaseCount('reports', 0);
    }

    public function test_member_commerce_is_tenant_scoped_on_tenant_domain_but_global_on_central_account(): void
    {
        [$tenantA, $domainA] = $this->createTenant('commerce-a.test');
        [$tenantB] = $this->createTenant('commerce-b.test');
        $member = $this->createUser('commerce-member@example.test');
        $tenantA->users()->attach($member->id, ['role' => 'member', 'status' => 'active']);
        $tenantB->users()->attach($member->id, ['role' => 'member', 'status' => 'active']);

        $eventA = $this->createEvent($tenantA, 'Tenant A Commerce Event');
        $eventB = $this->createEvent($tenantB, 'Tenant B Commerce Event');
        $orderA = $this->createOrderWithTicket($tenantA, $eventA, $member, 'QR-A');
        $orderB = $this->createOrderWithTicket($tenantB, $eventB, $member, 'QR-B');

        $this->actingAs($member)
            ->get('http://'.$domainA.'/orders')
            ->assertOk()
            ->assertSee('Tenant A Commerce Event')
            ->assertDontSee('Tenant B Commerce Event');

        $this->actingAs($member)
            ->get('http://'.$domainA.'/orders/'.$orderB->id)
            ->assertNotFound();

        $this->actingAs($member)
            ->get('http://'.$domainA.'/tickets')
            ->assertOk()
            ->assertSee('Tenant A Commerce Event')
            ->assertSee('QR-A')
            ->assertDontSee('Tenant B Commerce Event')
            ->assertDontSee('QR-B');

        $this->actingAs($member)
            ->get('http://platform.test/orders')
            ->assertOk()
            ->assertSee('Tenant A Commerce Event')
            ->assertSee('Tenant B Commerce Event');

        $this->actingAs($member)
            ->get('http://platform.test/orders/'.$orderA->id)
            ->assertOk();

        $this->actingAs($member)
            ->get('http://platform.test/orders/'.$orderB->id)
            ->assertOk();

        $this->actingAs($member)
            ->get('http://platform.test/tickets')
            ->assertOk()
            ->assertSee('Tenant A Commerce Event')
            ->assertSee('Tenant B Commerce Event')
            ->assertSee('QR-A')
            ->assertSee('QR-B');
    }

    private function createUser(string $email): User
    {
        return User::create([
            'name' => 'Isolation Test User',
            'display_name' => 'Isolation Test User',
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
            'name' => 'Isolation '.Str::before($domain, '.'),
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
            'visibility' => 'public',
            'rsvp_mode' => 'instant',
            'status' => 'published',
            'starts_at' => now()->addWeek(),
            'timezone' => 'America/Chicago',
            'capacity' => 50,
            'waitlist_enabled' => true,
            'exact_address_visibility' => 'approved_attendees',
        ]);
    }

    private function createOrderWithTicket(Tenant $tenant, Event $event, User $user, string $qr): Order
    {
        $ticketType = TicketType::create([
            'event_id' => $event->id,
            'name' => 'General Admission',
            'price_cents' => 2500,
            'currency' => 'USD',
            'quantity' => 50,
            'max_per_order' => 4,
            'active' => true,
        ]);

        $order = Order::create([
            'public_id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'event_id' => $event->id,
            'user_id' => $user->id,
            'status' => 'paid',
            'currency' => 'USD',
            'subtotal_cents' => 2500,
            'discount_cents' => 0,
            'fee_cents' => 0,
            'total_cents' => 2500,
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'ticket_type_id' => $ticketType->id,
            'quantity' => 1,
            'unit_price_cents' => 2500,
            'total_cents' => 2500,
        ]);

        Ticket::create([
            'public_id' => (string) Str::uuid(),
            'order_item_id' => $item->id,
            'user_id' => $user->id,
            'qr_token' => $qr,
            'status' => 'active',
        ]);

        return $order;
    }
}
