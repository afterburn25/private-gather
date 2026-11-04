<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PublicRouteCompatibilityTest extends TestCase
{
    public function test_legacy_clubs_route_name_is_always_available(): void
    {
        $this->assertTrue(Route::has('clubs.index'));

        $rendered = Blade::render("{{ route('clubs.index') }}");

        $this->assertStringContainsString('/clubs', $rendered);
    }

    public function test_legacy_clubs_url_redirects_to_public_organizations_browser(): void
    {
        $response = $this->get('http://platform.test/clubs');

        $response->assertRedirect('/organizations');
    }
}
