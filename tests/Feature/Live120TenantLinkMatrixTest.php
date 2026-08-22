<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Live120TenantLinkMatrixTest extends TestCase
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
            'demo.enabled' => false,
        ]);
    }

    public function test_public_tenant_navigation_links_do_not_return_500(): void
    {
        [$tenant] = $this->tenantFixture();

        foreach (['/', '/events', '/about', '/news'] as $path) {
            $response = $this->get('https://club.platform.test'.$path);
            $this->assertNotSame(500, $response->getStatusCode(), 'Unexpected tenant 500 for '.$path);
        }
    }

    public function test_member_tenant_navigation_links_do_not_return_500(): void
    {
        [$tenant, $member] = $this->tenantFixture('member');
        $this->actingAs($member);

        foreach ([
            '/dashboard',
            '/profile',
            '/community',
            '/community/chat',
            '/network',
            '/matches',
            '/messages',
            '/notifications',
            '/rewards',
        ] as $path) {
            $response = $this->get('https://club.platform.test'.$path);
            $this->assertNotSame(500, $response->getStatusCode(), 'Unexpected tenant member 500 for '.$path);
        }
    }

    public function test_manager_tenant_navigation_links_do_not_return_500(): void
    {
        [$tenant, $manager] = $this->tenantFixture('owner');
        $this->actingAs($manager);

        foreach ([
            '/manage',
            '/manage/domains',
            '/manage/cms/pages',
            '/manage/site-settings',
            '/manage/navigation',
            '/manage/branding',
            '/manage/media',
            '/manage/events',
            '/manage/orders',
            '/manage/analytics',
            '/manage/staff',
            '/manage/community/members',
            '/manage/community/news',
        ] as $path) {
            $response = $this->get('https://club.platform.test'.$path);
            $this->assertNotSame(500, $response->getStatusCode(), 'Unexpected tenant manager 500 for '.$path);
        }
    }

    /** @return array{0:Tenant,1:User} */
    private function tenantFixture(string $role = 'member'): array
    {
        $tenant = Tenant::create([
            'name' => 'Tenant Link Matrix Club',
            'slug' => 'tenant-link-matrix-club',
            'type' => Tenant::TYPE_CLUB,
            'status' => 'active',
            'plan' => 'starter',
            'settings' => ['marketplace_enabled' => true],
        ]);

        TenantDomain::create([
            'tenant_id' => $tenant->id,
            'domain' => 'club.platform.test',
            'type' => TenantDomain::TYPE_PLATFORM_SUBDOMAIN,
            'is_primary' => true,
            'status' => TenantDomain::STATUS_ACTIVE,
            'verified_at' => now(),
            'ssl_status' => 'active',
            'dns_status' => 'active',
        ]);

        $user = User::create([
            'name' => $role === 'owner' ? 'Tenant Matrix Owner' : 'Tenant Matrix Member',
            'display_name' => $role === 'owner' ? 'Tenant Matrix Owner' : 'Tenant Matrix Member',
            'email' => $role.'-tenant-matrix@example.test',
            'password' => 'Password123',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'status' => 'active',
            'is_platform_admin' => false,
            'adult_confirmed_at' => now(),
            'terms_accepted_at' => now(),
            'privacy_accepted_at' => now(),
            'privacy_version' => '1.0',
        ]);

        $tenant->users()->attach($user->id, [
            'role' => $role,
            'status' => 'active',
        ]);

        return [$tenant, $user];
    }
}
