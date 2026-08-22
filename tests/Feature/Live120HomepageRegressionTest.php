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
        $member = User::create([
            'name' => 'Production Repro Member',
            'display_name' => 'Production Repro Member',
            'email' => 'production-repro@example.test',
            'password' => 'Password123',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'status' => 'active',
            'adult_confirmed_at' => now(),
            'terms_accepted_at' => now(),
            'privacy_accepted_at' => now(),
            'privacy_version' => '1.0',
        ]);

        $this->actingAs($member)
            ->get('http://platform.test/')
            ->assertOk()
            ->assertSee('Exclusive events.', false)
            ->assertSee('My Clubs', false);
    }
}
