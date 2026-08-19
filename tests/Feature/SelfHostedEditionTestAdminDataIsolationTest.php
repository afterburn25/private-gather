<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SelfHostedEditionTestAdminDataIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_and_moderation_are_scoped_to_configured_self_hosted_tenant(): void
    {
        config([
            'app.url' => 'http://platform.test',
            'edition.name' => 'self_hosted',
            'edition.self_hosted.visibility' => 'private',
            'edition.self_hosted.registration' => 'approval',
            'platform.root_domain' => 'platform.test',
            'platform.central_domains' => ['platform.test'],
        ]);

        $localTenant = $this->createTenant('Local Club', 'local-club');
        $otherTenant = $this->createTenant('Imported Other Club', 'other-club');
        config(['edition.self_hosted.tenant_id' => $localTenant->id]);

        $admin = $this->createUser('admin@example.test', true);
        $localMember = $this->createUser('local@example.test');
        $outsideMember = $this->createUser('outside@example.test');

        $localTenant->users()->attach($admin->id, ['role' => 'owner', 'status' => 'active']);
        $localTenant->users()->attach($localMember->id, ['role' => 'member', 'status' => 'active']);
        $otherTenant->users()->attach($outsideMember->id, ['role' => 'member', 'status' => 'active']);

        $localReport = Report::create([
            'reporter_id' => $localMember->id,
            'tenant_id' => $localTenant->id,
            'reportable_type' => 'tenant',
            'reportable_id' => $localTenant->id,
            'category' => 'other',
            'details' => 'local-only-report',
            'status' => 'open',
        ]);
        $outsideReport = Report::create([
            'reporter_id' => $outsideMember->id,
            'tenant_id' => $otherTenant->id,
            'reportable_type' => 'tenant',
            'reportable_id' => $otherTenant->id,
            'category' => 'other',
            'details' => 'outside-report-must-not-leak',
            'status' => 'open',
        ]);

        $this->actingAs($admin)
            ->get('http://platform.test/admin')
            ->assertOk()
            ->assertViewHas('stats', function (array $stats): bool {
                return $stats['members'] === 2 && $stats['open_reports'] === 1;
            });

        $this->actingAs($admin)
            ->get('http://platform.test/admin/moderation')
            ->assertOk()
            ->assertSee('local-only-report')
            ->assertDontSee('outside-report-must-not-leak');

        $this->actingAs($admin)
            ->patch('http://platform.test/admin/moderation/'.$outsideReport->id, ['status' => 'resolved'])
            ->assertNotFound();

        $this->assertSame('open', $outsideReport->fresh()->status);
        $this->assertSame('open', $localReport->fresh()->status);
    }

    private function createTenant(string $name, string $slug): Tenant
    {
        return Tenant::create([
            'name' => $name,
            'slug' => $slug,
            'type' => Tenant::TYPE_CLUB,
            'status' => 'active',
            'plan' => 'self_hosted',
            'settings' => ['marketplace_enabled' => false],
        ]);
    }

    private function createUser(string $email, bool $platformAdmin = false): User
    {
        return User::create([
            'name' => 'Test User',
            'display_name' => 'Test User',
            'email' => $email,
            'password' => 'Password1234',
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
