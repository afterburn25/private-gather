<?php

namespace Tests\Feature;

use App\Models\CmsNavigationItem;
use App\Models\Tenant;
use App\Models\TenantDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoredNavigationUrlSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_unsafe_navigation_scheme_is_neutralized_when_rendered(): void
    {
        config(['platform.central_domains' => ['platform.test']]);

        $tenant = Tenant::create([
            'name' => 'Safe Navigation Club',
            'slug' => 'safe-navigation-club',
            'type' => Tenant::TYPE_CLUB,
            'status' => 'active',
            'plan' => 'starter',
            'settings' => ['marketplace_enabled' => true],
        ]);

        TenantDomain::create([
            'tenant_id' => $tenant->id,
            'domain' => 'safe-navigation.test',
            'type' => TenantDomain::TYPE_CUSTOM_DOMAIN,
            'is_primary' => true,
            'status' => TenantDomain::STATUS_ACTIVE,
            'verified_at' => now(),
            'ssl_status' => 'active',
            'dns_status' => 'active',
        ]);

        CmsNavigationItem::create([
            'tenant_id' => $tenant->id,
            'location' => 'header',
            'label' => 'Legacy Unsafe Link',
            'url' => 'javascript:alert(1)',
            'sort_order' => 10,
            'is_enabled' => true,
        ]);

        CmsNavigationItem::create([
            'tenant_id' => $tenant->id,
            'location' => 'header',
            'label' => 'Safe External Link',
            'url' => 'https://example.test/safe',
            'sort_order' => 20,
            'is_enabled' => true,
        ]);

        $this->get('http://safe-navigation.test/')
            ->assertOk()
            ->assertSee('Legacy Unsafe Link')
            ->assertDontSee('javascript:alert(1)', false)
            ->assertSee('https://example.test/safe', false);
    }
}
