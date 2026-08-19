<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsCsvSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_neutralizes_formula_like_event_titles(): void
    {
        $tenant = Tenant::create([
            'name' => 'CSV Safety Club',
            'slug' => 'csv-safety-club',
            'type' => Tenant::TYPE_CLUB,
            'status' => 'active',
            'plan' => 'starter',
            'settings' => ['marketplace_enabled' => true],
        ]);

        TenantDomain::create([
            'tenant_id' => $tenant->id,
            'domain' => 'csv-safety.test',
            'type' => TenantDomain::TYPE_CUSTOM_DOMAIN,
            'is_primary' => true,
            'status' => TenantDomain::STATUS_ACTIVE,
            'verified_at' => now(),
            'ssl_status' => 'active',
            'dns_status' => 'active',
        ]);

        $owner = User::create([
            'name' => 'CSV Owner',
            'display_name' => 'CSV Owner',
            'email' => 'csv-owner@example.test',
            'password' => 'Password123',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'status' => 'active',
            'adult_confirmed_at' => now(),
            'terms_accepted_at' => now(),
            'privacy_accepted_at' => now(),
            'privacy_version' => '1.0',
        ]);
        $tenant->users()->attach($owner->id, ['role' => 'owner', 'status' => 'active']);

        Event::create([
            'tenant_id' => $tenant->id,
            'title' => '=HYPERLINK("https://example.test","click")',
            'slug' => 'csv-formula-event',
            'visibility' => 'public',
            'rsvp_mode' => 'instant',
            'status' => 'published',
            'starts_at' => now()->addWeek(),
            'timezone' => 'America/Chicago',
            'capacity' => 50,
            'waitlist_enabled' => true,
            'exact_address_visibility' => 'approved_attendees',
        ]);

        $response = $this->actingAs($owner)
            ->get('http://csv-safety.test/manage/analytics/export')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('x-content-type-options', 'nosniff');

        ob_start();
        $response->baseResponse->sendContent();
        $csv = (string) ob_get_clean();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString("\n=HYPERLINK", $csv);
    }
}
