<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MemberDashboardTenantIsolationTest extends TestCase
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

    public function test_tenant_dashboard_shows_only_current_tenant_but_central_dashboard_remains_aggregate(): void
    {
        [$tenantA, $domainA] = $this->createTenant('dashboard-a.test');
        [$tenantB] = $this->createTenant('dashboard-b.test');
        $member = $this->createUser('dashboard-member@example.test');
        $tenantA->users()->attach($member->id, ['role' => 'member', 'status' => 'active']);
        $tenantB->users()->attach($member->id, ['role' => 'member', 'status' => 'active']);

        $eventA = $this->createEvent($tenantA, 'Tenant A Dashboard Event');
        $eventB = $this->createEvent($tenantB, 'Tenant B Dashboard Event');
        $this->createRsvp($eventA, $member);
        $this->createRsvp($eventB, $member);

        $tenantResponse = $this->actingAs($member)
            ->get('http://'.$domainA.'/dashboard')
            ->assertOk()
            ->assertSee('Tenant A Dashboard Event')
            ->assertDontSee('Tenant B Dashboard Event');

        $this->assertCount(1, $tenantResponse->viewData('rsvps'));
        $this->assertCount(1, $tenantResponse->viewData('user')->tenants);
        $this->assertSame($tenantA->id, $tenantResponse->viewData('user')->tenants->first()->id);

        $centralResponse = $this->actingAs($member)
            ->get('http://platform.test/dashboard')
            ->assertOk()
            ->assertSee('Tenant A Dashboard Event')
            ->assertSee('Tenant B Dashboard Event');

        $this->assertCount(2, $centralResponse->viewData('rsvps'));
        $this->assertCount(2, $centralResponse->viewData('user')->tenants);
    }

    private function createUser(string $email): User
    {
        return User::create([
            'name' => 'Dashboard Test User',
            'display_name' => 'Dashboard Test User',
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
            'name' => 'Dashboard '.Str::before($domain, '.'),
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

    private function createRsvp(Event $event, User $user): void
    {
        EventRsvp::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'status' => 'approved',
            'guest_count' => 1,
            'answers' => [],
            'approved_at' => now(),
        ]);
    }
}
