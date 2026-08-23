<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class V123GrowthCommerceRuntimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.url' => 'https://platform.test',
            'edition.name' => 'hosted',
            'platform.root_domain' => 'platform.test',
            'platform.central_domains' => ['platform.test', 'www.platform.test'],
            'platform.tenant_scheme' => 'https',
            'platform.tenant_mount_path' => '',
            'platform.reserved_subdomains' => ['www', 'admin', 'api', 'mail'],
            'demo.enabled' => false,
        ]);
    }

    public function test_growth_console_renders_and_membership_price_is_tenant_scoped(): void
    {
        [$tenant, $manager] = $this->tenantManager('growth');

        $this->actingAs($manager)
            ->get('https://growth.platform.test/manage/growth')
            ->assertOk()
            ->assertSee('Growth & Commerce');

        $this->actingAs($manager)
            ->post('https://growth.platform.test/manage/growth/membership-levels', [
                'name' => 'Gold',
                'code' => 'gold',
                'price' => '49.95',
                'currency' => 'USD',
                'billing_interval' => 'monthly',
                'ticket_discount_percent' => 10,
                'sort_order' => 10,
                'application_required' => 1,
                'active' => 1,
                'benefits' => "Early access\nMember pricing",
            ])->assertRedirect();

        $this->assertDatabaseHas('membership_levels', [
            'tenant_id' => $tenant->id,
            'code' => 'gold',
            'price_cents' => 4995,
            'currency' => 'USD',
        ]);
    }

    public function test_manual_marketing_contact_does_not_gain_consent_unless_explicitly_selected(): void
    {
        [$tenant, $manager] = $this->tenantManager('consent');

        $this->actingAs($manager)
            ->post('https://consent.platform.test/manage/growth/contacts', [
                'first_name' => 'No',
                'last_name' => 'Consent',
                'email' => 'no-consent@example.test',
            ])->assertRedirect();

        $this->assertDatabaseHas('marketing_contacts', [
            'tenant_id' => $tenant->id,
            'email' => 'no-consent@example.test',
            'email_opt_in' => false,
            'sms_opt_in' => false,
        ]);
        $contact = \DB::table('marketing_contacts')->where('email', 'no-consent@example.test')->first();
        $this->assertNull($contact?->email_consented_at);
        $this->assertNull($contact?->sms_consented_at);

        $this->actingAs($manager)
            ->post('https://consent.platform.test/manage/growth/contacts', [
                'first_name' => 'Email',
                'last_name' => 'Consent',
                'email' => 'opted-in@example.test',
                'email_opt_in' => 1,
            ])->assertRedirect();

        $opted = \DB::table('marketing_contacts')->where('email', 'opted-in@example.test')->first();
        $this->assertNotNull($opted);
        $this->assertSame(1, (int) $opted->email_opt_in);
        $this->assertNotNull($opted->email_consented_at);
    }

    public function test_event_addon_route_rejects_event_from_another_tenant(): void
    {
        [, $manager] = $this->tenantManager('owner-club');
        [$other] = $this->tenantManager('other-club');
        $event = Event::create([
            'tenant_id' => $other->id,
            'title' => 'Other Club Event',
            'slug' => 'other-club-event',
            'visibility' => 'members',
            'rsvp_mode' => 'instant',
            'status' => 'published',
            'starts_at' => now()->addDay(),
            'timezone' => 'America/Chicago',
        ]);

        $this->actingAs($manager)
            ->post('https://owner-club.platform.test/manage/events/'.$event->id.'/addons', [
                'name' => 'Should Fail',
                'type' => 'other',
                'price' => '5.00',
                'currency' => 'USD',
                'max_per_order' => 1,
                'sort_order' => 10,
                'active' => 1,
            ])->assertNotFound();

        $this->assertDatabaseMissing('event_addons', ['event_id' => $event->id, 'name' => 'Should Fail']);
    }

    public function test_existing_organization_without_platform_domain_can_provision_one_once(): void
    {
        $tenant = Tenant::create([
            'name' => 'Legacy Organization',
            'slug' => 'legacy-organization',
            'type' => Tenant::TYPE_CLUB,
            'status' => 'active',
            'plan' => 'starter',
            'settings' => [],
        ]);
        $owner = $this->user('legacy-owner');
        $tenant->users()->attach($owner->id, ['role' => 'owner', 'status' => 'active']);

        $this->actingAs($owner)
            ->post('https://platform.test/my-organizations/'.$tenant->id.'/provision-subdomain', [
                'subdomain' => 'Legacy Night',
            ])->assertRedirect();

        $this->assertDatabaseHas('tenant_domains', [
            'tenant_id' => $tenant->id,
            'domain' => 'legacy-night.platform.test',
            'type' => TenantDomain::TYPE_PLATFORM_SUBDOMAIN,
            'status' => TenantDomain::STATUS_ACTIVE,
        ]);
    }

    /** @return array{0:Tenant,1:User} */
    private function tenantManager(string $subdomain): array
    {
        $tenant = Tenant::create([
            'name' => ucwords(str_replace('-', ' ', $subdomain)),
            'slug' => $subdomain,
            'type' => Tenant::TYPE_CLUB,
            'status' => 'active',
            'plan' => 'starter',
            'settings' => [],
        ]);
        TenantDomain::create([
            'tenant_id' => $tenant->id,
            'domain' => $subdomain.'.platform.test',
            'type' => TenantDomain::TYPE_PLATFORM_SUBDOMAIN,
            'is_primary' => true,
            'status' => TenantDomain::STATUS_ACTIVE,
            'verified_at' => now(),
            'ssl_status' => 'managed',
            'dns_status' => 'active',
            'dns_last_checked_at' => now(),
            'redirect_to_primary' => false,
        ]);
        $manager = $this->user($subdomain.'-manager');
        $tenant->users()->attach($manager->id, ['role' => 'manager', 'status' => 'active']);

        return [$tenant, $manager];
    }

    private function user(string $username): User
    {
        return User::create([
            'name' => $username,
            'username' => $username,
            'display_name' => $username,
            'email' => $username.'@example.test',
            'password' => 'Password12345',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'status' => 'active',
            'is_platform_admin' => false,
            'adult_confirmed_at' => now(),
            'terms_accepted_at' => now(),
            'privacy_accepted_at' => now(),
            'privacy_version' => '1.0',
        ]);
    }
}
