<?php

namespace Tests\Feature;

use App\Models\DataRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivacyRequestAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_hosted_platform_admin_can_track_privacy_request_lifecycle(): void
    {
        config([
            'edition.name' => 'hosted',
            'platform.root_domain' => 'platform.test',
            'platform.central_domains' => ['platform.test'],
        ]);

        $admin = $this->createUser('privacy-admin@example.test', true);
        $member = $this->createUser('privacy-member@example.test');
        $privacyRequest = DataRequest::create([
            'user_id' => $member->id,
            'type' => 'export',
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get('http://platform.test/admin/privacy-requests')
            ->assertOk()
            ->assertSee('privacy-member@example.test')
            ->assertSee('Export request');

        $this->actingAs($admin)
            ->patch('http://platform.test/admin/privacy-requests/'.$privacyRequest->id, [
                'status' => 'processing',
                'admin_notes' => 'Preparing the requested export.',
            ])
            ->assertRedirect();

        $privacyRequest->refresh();
        $this->assertSame('processing', $privacyRequest->status);
        $this->assertNull($privacyRequest->completed_at);
        $this->assertSame('Preparing the requested export.', $privacyRequest->admin_notes);

        $this->actingAs($admin)
            ->patch('http://platform.test/admin/privacy-requests/'.$privacyRequest->id, [
                'status' => 'completed',
                'admin_notes' => 'Fulfillment completed outside the application.',
            ])
            ->assertRedirect();

        $privacyRequest->refresh();
        $this->assertSame('completed', $privacyRequest->status);
        $this->assertNotNull($privacyRequest->completed_at);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'privacy.request.updated',
            'subject_type' => DataRequest::class,
            'subject_id' => $privacyRequest->id,
        ]);
    }

    public function test_non_admin_cannot_access_privacy_operations(): void
    {
        config([
            'edition.name' => 'hosted',
            'platform.root_domain' => 'platform.test',
            'platform.central_domains' => ['platform.test'],
        ]);

        $member = $this->createUser('ordinary-member@example.test');

        $this->actingAs($member)
            ->get('http://platform.test/admin/privacy-requests')
            ->assertForbidden();
    }

    public function test_self_hosted_privacy_queue_is_limited_to_local_organization_users(): void
    {
        config([
            'app.url' => 'http://platform.test',
            'edition.name' => 'self_hosted',
            'edition.self_hosted.visibility' => 'private',
            'edition.self_hosted.registration' => 'approval',
            'platform.root_domain' => 'platform.test',
            'platform.central_domains' => ['platform.test'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Local Privacy Club',
            'slug' => 'local-privacy-club',
            'type' => Tenant::TYPE_PRIVATE_HOST,
            'status' => 'active',
            'plan' => 'self_hosted',
            'settings' => ['marketplace_enabled' => false, 'self_hosted' => true],
        ]);
        config(['edition.self_hosted.tenant_id' => $tenant->id]);

        $admin = $this->createUser('local-privacy-admin@example.test', true);
        $localMember = $this->createUser('local-request@example.test');
        $foreignMember = $this->createUser('foreign-request@example.test');
        $tenant->users()->attach($admin->id, ['role' => 'owner', 'status' => 'active']);
        $tenant->users()->attach($localMember->id, ['role' => 'member', 'status' => 'active']);

        $localRequest = DataRequest::create([
            'user_id' => $localMember->id,
            'type' => 'delete',
            'status' => 'pending',
            'requested_at' => now(),
        ]);
        $foreignRequest = DataRequest::create([
            'user_id' => $foreignMember->id,
            'type' => 'export',
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get('http://platform.test/admin/privacy-requests')
            ->assertOk()
            ->assertSee('local-request@example.test')
            ->assertDontSee('foreign-request@example.test');

        $this->actingAs($admin)
            ->patch('http://platform.test/admin/privacy-requests/'.$foreignRequest->id, [
                'status' => 'processing',
            ])
            ->assertNotFound();

        $this->assertSame('pending', $foreignRequest->fresh()->status);

        $this->actingAs($admin)
            ->patch('http://platform.test/admin/privacy-requests/'.$localRequest->id, [
                'status' => 'processing',
                'admin_notes' => 'Local request under review.',
            ])
            ->assertRedirect();

        $this->assertSame('processing', $localRequest->fresh()->status);
    }

    private function createUser(string $email, bool $platformAdmin = false): User
    {
        return User::create([
            'name' => 'Privacy Operations User',
            'display_name' => 'Privacy Operations User',
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
