<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_central_domain_loads_marketplace_homepage(): void
    {
        config(['platform.central_domains' => ['platform.test']]);
        $this->get('http://platform.test/')->assertOk()->assertSee('Discover your next social experience.');
    }

    public function test_active_tenant_domain_loads_tenant_homepage(): void
    {
        config(['platform.central_domains' => ['platform.test']]);
        $tenant = Tenant::query()->create(['name' => 'Sunset Club', 'slug' => 'sunset-club', 'type' => 'club', 'status' => 'active', 'plan' => 'starter']);
        TenantDomain::query()->create(['tenant_id' => $tenant->id, 'domain' => 'sunset.test', 'type' => 'custom_domain', 'is_primary' => true, 'status' => 'active', 'verified_at' => now(), 'ssl_status' => 'active']);
        $this->get('http://sunset.test/')->assertOk()->assertSee('Sunset Club');
    }

    public function test_unknown_domain_fails_closed(): void
    {
        config(['platform.central_domains' => ['platform.test']]);
        $this->get('http://unknown.test/')->assertNotFound()->assertSee('not active yet');
    }
}
