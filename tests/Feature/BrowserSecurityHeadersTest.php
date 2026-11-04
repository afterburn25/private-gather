<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrowserSecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['platform.central_domains' => ['platform.test']]);
    }

    public function test_public_pages_receive_safe_browser_baseline_headers(): void
    {
        $this->get('http://platform.test/about')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Permitted-Cross-Domain-Policies', 'none')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'; object-src 'none'; base-uri 'self'")
            ->assertHeader('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(), payment=(self), usb=(), serial=()');
    }

    public function test_token_bearing_invitation_route_never_leaks_referrer_or_cache_state(): void
    {
        $response = $this->get('http://platform.test/event-invite/'.str_repeat('a', 64));

        $response->assertRedirect()
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive, nosnippet')
            ->assertHeader('Pragma', 'no-cache');

        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);
    }

    public function test_password_recovery_page_is_non_referring_non_indexable_and_non_cacheable(): void
    {
        $response = $this->get('http://platform.test/reset-password/example-recovery-token?email=member%40example.test');

        $response->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive, nosnippet');

        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);
    }

    public function test_authenticated_pages_are_not_persistently_cached_even_on_public_routes(): void
    {
        $user = User::create([
            'name' => 'Browser Boundary Member',
            'display_name' => 'Browser Boundary Member',
            'email' => 'browser-boundary@example.test',
            'password' => 'Password123',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'status' => 'active',
            'adult_confirmed_at' => now(),
            'terms_accepted_at' => now(),
            'privacy_accepted_at' => now(),
            'privacy_version' => '1.0',
        ]);

        $response = $this->actingAs($user)->get('http://platform.test/about')->assertOk();
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);
    }
}
