<?php

namespace Tests\Feature;

use App\Models\CmsNavigationItem;
use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\EventRsvp;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ObjectOwnershipSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['platform.central_domains' => ['platform.test']]);
    }

    public function test_member_cannot_probe_another_members_order_id(): void
    {
        [$tenant] = $this->createTenant('member-order-owner.test');
        $event = $this->createEvent($tenant, 'Member Order Event');
        $owner = $this->createUser('order-owner@example.test');
        $outsider = $this->createUser('order-outsider@example.test');
        $order = $this->createOrder($tenant, $event, $owner);

        $this->actingAs($outsider)
            ->get('http://platform.test/orders/'.$order->id)
            ->assertNotFound();
    }

    public function test_tenant_manager_cannot_modify_another_tenants_navigation_item(): void
    {
        [$tenantA, $domainA] = $this->createTenant('tenant-a-nav.test');
        [$tenantB] = $this->createTenant('tenant-b-nav.test');
        $ownerA = $this->createUser('owner-a-nav@example.test');
        $tenantA->users()->attach($ownerA->id, ['role' => 'owner', 'status' => 'active']);
        $itemB = CmsNavigationItem::create([
            'tenant_id' => $tenantB->id,
            'location' => 'header',
            'label' => 'Tenant B Only',
            'url' => '/events',
            'sort_order' => 10,
            'is_enabled' => true,
        ]);

        $this->actingAs($ownerA)
            ->patch('http://'.$domainA.'/manage/navigation/'.$itemB->id, [
                'label' => 'Hijacked',
                'url' => '/about',
                'sort_order' => 1,
                'is_enabled' => '1',
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('cms_navigation_items', [
            'id' => $itemB->id,
            'tenant_id' => $tenantB->id,
            'label' => 'Tenant B Only',
            'url' => '/events',
        ]);
    }

    public function test_tenant_manager_cannot_mark_another_tenants_order_paid(): void
    {
        [$tenantA, $domainA] = $this->createTenant('tenant-a-orders.test');
        [$tenantB] = $this->createTenant('tenant-b-orders.test');
        $ownerA = $this->createUser('owner-a-orders@example.test');
        $buyerB = $this->createUser('buyer-b-orders@example.test');
        $tenantA->users()->attach($ownerA->id, ['role' => 'owner', 'status' => 'active']);
        $eventB = $this->createEvent($tenantB, 'Tenant B Paid Event');
        $orderB = $this->createOrder($tenantB, $eventB, $buyerB);

        $this->actingAs($ownerA)
            ->post('http://'.$domainA.'/manage/orders/'.$orderB->id.'/paid')
            ->assertNotFound();

        $this->assertDatabaseHas('orders', [
            'id' => $orderB->id,
            'tenant_id' => $tenantB->id,
            'status' => 'pending',
        ]);
    }

    public function test_checkin_staff_cannot_attach_arbitrary_platform_member_to_event(): void
    {
        [$tenant, $domain] = $this->createTenant('checkin-entitlement.test');
        $event = $this->createEvent($tenant, 'Entitlement Event');
        $staff = $this->createUser('checkin-staff@example.test');
        $unrelatedMember = $this->createUser('unrelated-member@example.test');
        $tenant->users()->attach($staff->id, ['role' => 'checkin', 'status' => 'active']);

        $this->actingAs($staff)
            ->from('http://'.$domain.'/manage/events/'.$event->id.'/check-in')
            ->post('http://'.$domain.'/manage/events/'.$event->id.'/check-in/manual', [
                'user_id' => $unrelatedMember->id,
                'guest_count' => 1,
            ])
            ->assertStatus(422);

        $this->assertDatabaseMissing('event_checkins', [
            'event_id' => $event->id,
            'user_id' => $unrelatedMember->id,
        ]);
    }

    public function test_approved_rsvp_allows_manual_checkin_only_up_to_guest_entitlement(): void
    {
        [$tenant, $domain] = $this->createTenant('checkin-limit.test');
        $event = $this->createEvent($tenant, 'Checkin Limit Event');
        $staff = $this->createUser('limit-checkin-staff@example.test');
        $attendee = $this->createUser('limit-attendee@example.test');
        $tenant->users()->attach($staff->id, ['role' => 'checkin', 'status' => 'active']);
        EventRsvp::create([
            'event_id' => $event->id,
            'user_id' => $attendee->id,
            'status' => 'approved',
            'guest_count' => 2,
            'answers' => [],
            'approved_at' => now(),
        ]);

        $this->actingAs($staff)
            ->from('http://'.$domain.'/manage/events/'.$event->id.'/check-in')
            ->post('http://'.$domain.'/manage/events/'.$event->id.'/check-in/manual', [
                'user_id' => $attendee->id,
                'guest_count' => 2,
            ])
            ->assertRedirect('http://'.$domain.'/manage/events/'.$event->id.'/check-in');

        $this->assertSame(2, (int) EventCheckin::where('event_id', $event->id)
            ->where('user_id', $attendee->id)
            ->sum('guest_count'));

        $this->actingAs($staff)
            ->from('http://'.$domain.'/manage/events/'.$event->id.'/check-in')
            ->post('http://'.$domain.'/manage/events/'.$event->id.'/check-in/manual', [
                'user_id' => $attendee->id,
                'guest_count' => 1,
            ])
            ->assertStatus(422);

        $this->assertSame(2, (int) EventCheckin::where('event_id', $event->id)
            ->where('user_id', $attendee->id)
            ->sum('guest_count'));
    }

    public function test_nonpublished_event_rejects_manual_checkin(): void
    {
        [$tenant, $domain] = $this->createTenant('draft-checkin.test');
        $event = $this->createEvent($tenant, 'Draft Checkin Event', 'draft');
        $staff = $this->createUser('draft-checkin-staff@example.test');
        $tenant->users()->attach($staff->id, ['role' => 'checkin', 'status' => 'active']);

        $this->actingAs($staff)
            ->post('http://'.$domain.'/manage/events/'.$event->id.'/check-in/manual', [
                'guest_count' => 1,
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('event_checkins', 0);
    }

    private function createUser(string $email): User
    {
        return User::create([
            'name' => 'Ownership Test User',
            'display_name' => 'Ownership Test User',
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
            'name' => 'Ownership '.Str::before($domain, '.'),
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

    private function createEvent(Tenant $tenant, string $title, string $status = 'published'): Event
    {
        return Event::create([
            'tenant_id' => $tenant->id,
            'title' => $title,
            'slug' => Str::slug($title),
            'visibility' => 'public',
            'rsvp_mode' => 'instant',
            'status' => $status,
            'starts_at' => now()->addWeek(),
            'timezone' => 'America/Chicago',
            'capacity' => 50,
            'waitlist_enabled' => true,
            'exact_address_visibility' => 'approved_attendees',
        ]);
    }

    private function createOrder(Tenant $tenant, Event $event, User $user): Order
    {
        return Order::create([
            'public_id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'event_id' => $event->id,
            'user_id' => $user->id,
            'status' => 'pending',
            'currency' => 'USD',
            'subtotal_cents' => 2500,
            'discount_cents' => 0,
            'fee_cents' => 0,
            'total_cents' => 2500,
        ]);
    }
}
