<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Live120TenantSubdomainRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_platform_subdomain_resolves_tenant_and_renders_club_homepage(): void
    {
        config([
            'edition.name' => 'hosted',
            'platform.root_domain' => 'platform.test',
            'platform.central_domains' => ['platform.test', 'www.platform.test'],
            'platform.tenant_scheme' => 'https',
            'platform.tenant_mount_path' => '',
        ]);

        $tenant = Tenant::create([
            'name' => 'Subdomain Repro Club',
            'slug' => 'subdomain-repro-club',
            'type' => Tenant::TYPE_CLUB,
            'status' => 'active',
            'plan' => 'starter',
            'settings' => ['marketplace_enabled' => true],
        ]);

        $domain = TenantDomain::create([
            'tenant_id' => $tenant->id,
            'domain' => 'club.platform.test',
            'type' => TenantDomain::TYPE_PLATFORM_SUBDOMAIN,
            'is_primary' => true,
            'status' => TenantDomain::STATUS_PENDING,
            'verified_at' => null,
            'ssl_status' => 'pending',
            'dns_status' => 'pending',
        ]);

        $this->get('https://club.platform.test/')
            ->assertOk()
            ->assertSee('Subdomain Repro Club', false)
            ->assertSee('No homepage has been published yet.', false);

        $domain->refresh();

        $this->assertSame(TenantDomain::STATUS_ACTIVE, $domain->status);
        $this->assertNotNull($domain->verified_at);
        $this->assertSame('active', $domain->dns_status);
        $this->assertNull($domain->last_error);
        $this->assertTrue((bool) ($domain->health['accepted'] ?? false));
    }
}
