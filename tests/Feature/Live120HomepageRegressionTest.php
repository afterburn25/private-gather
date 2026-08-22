<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Live120HomepageRegressionTest extends TestCase
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

    public function test_live_120_hosted_discovery_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('clubs.index'));
        $this->assertTrue(Route::has('clubs.show'));
        $this->assertTrue(Route::has('affiliate.go'));
    }

    public function test_live_120_homepage_renders_for_anonymous_visitor(): void
    {
        $this->get('http://platform.test/')
            ->assertOk()
            ->assertSee('Exclusive events.', false)
            ->assertSee('PRIVATE CLUBS', false);
    }

    public function test_live_120_homepage_renders_for_authenticated_active_member(): void
    {
        $member = $this->member('production-repro@example.test');

        $this->actingAs($member)
            ->get('http://platform.test/')
            ->assertOk()
            ->assertSee('Exclusive events.', false)
            ->assertSee('My Clubs', false);
    }

    public function test_live_120_homepage_renders_for_authenticated_platform_admin(): void
    {
        $admin = $this->member('production-admin-repro@example.test', true);

        $this->actingAs($admin)
            ->get('http://platform.test/')
            ->assertOk()
            ->assertSee('Exclusive events.', false)
            ->assertSee('Admin', false)
            ->assertSee('My Clubs', false);
    }

    private function member(string $email, bool $platformAdmin = false): User
    {
        return User::create([
            'name' => $platformAdmin ? 'Production Admin Repro' : 'Production Repro Member',
            'display_name' => $platformAdmin ? 'Production Admin Repro' : 'Production Repro Member',
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
