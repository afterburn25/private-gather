<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Live120RouteMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'http://platform.test',
            'edition.name' => 'hosted',
            'platform.root_domain' => 'platform.test',
            'platform.central_domains' => ['platform.test'],
            'demo.enabled' => false,
        ]);
    }

    public function test_public_and_auth_pages_do_not_return_500(): void
    {
        foreach (['/', '/events', '/organizations', '/about', '/login', '/register', '/clubs'] as $path) {
            $response = $this->get('http://platform.test'.$path);
            $this->assertNotSame(500, $response->getStatusCode(), 'Unexpected 500 for '.$path);
        }
    }

    public function test_member_pages_do_not_return_500(): void
    {
        $member = $this->user('route-matrix-member@example.test');
        $this->actingAs($member);

        foreach (['/dashboard', '/profile', '/messages', '/orders', '/tickets', '/security', '/my-organizations'] as $path) {
            $response = $this->get('http://platform.test'.$path);
            $this->assertNotSame(500, $response->getStatusCode(), 'Unexpected 500 for '.$path);
        }
    }

    public function test_platform_admin_pages_do_not_return_500(): void
    {
        $admin = $this->user('route-matrix-admin@example.test', true);
        $this->actingAs($admin);

        foreach ([
            '/admin',
            '/admin/users',
            '/admin/organizations',
            '/admin/plans',
            '/admin/website-content',
            '/admin/moderation',
            '/admin/privacy-requests',
            '/admin/system-health',
            '/admin/upgrades',
        ] as $path) {
            $response = $this->get('http://platform.test'.$path);
            $this->assertNotSame(500, $response->getStatusCode(), 'Unexpected 500 for '.$path);
        }
    }

    private function user(string $email, bool $platformAdmin = false): User
    {
        return User::create([
            'name' => $platformAdmin ? 'Route Matrix Admin' : 'Route Matrix Member',
            'display_name' => $platformAdmin ? 'Route Matrix Admin' : 'Route Matrix Member',
            'email' => $email,
            'password' => 'Password123',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'status' => 'active',
            'is_platform_admin' => $platformAdmin,
            'adult_confirmed_at' => now(),
            'terms_accepted_at' => now(),
            'privacy_accepted_at' => now(),
            'privacy_version' => '1.0',
        ]);
    }
}
