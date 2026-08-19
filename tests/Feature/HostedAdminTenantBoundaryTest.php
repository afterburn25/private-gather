<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HostedAdminTenantBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_hosted_tenant_domain_never_exposes_platform_admin_surface(): void
    {
        config([
            'edition.name' => 'hosted',
            'platform.root_domain' => 'platform.test',
            'platform.central_domains' => ['platform.test'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Hosted Club',
            'slug' => 'hosted-club',
            'type' => Tenant::TYPE_CLUB,
            'status' => 'active',
            'plan' => 'starter',
        ]);

        TenantDomain::create([
            'tenant_id' => $tenant->id,
            'domain' => 'club.platform.test',
            'type' => TenantDomain::TYPE_PLATFORM_SUBDOMAIN,
            'is_primary' => true,
            'status' => TenantDomain::STATUS_ACTIVE,
            'ssl_status' => 'active',
            'dns_status' => 'active',
        ]);

        $this->get('http://club.platform.test/admin')->assertNotFound();

        $admin = User::create([
            'name' => 'Platform Admin',
            'display_name' => 'Platform Admin',
            'email' => 'platform-admin@example.test',
            'password' => 'Password1234',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'status' => 'active',
            'is_platform_admin' => true,
            'adult_confirmed_at' => now(),
            'terms_accepted_at' => now(),
            'privacy_accepted_at' => now(),
            'privacy_version' => '1.0',
        ]);

        $this->actingAs($admin)
            ->get('http://club.platform.test/admin')
            ->assertNotFound();
    }
}
