<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantMembershipApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipApplicationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'http://platform.test',
            'platform.root_domain' => 'platform.test',
            'platform.central_domains' => ['platform.test'],
        ]);
    }

    public function test_adult_member_can_apply_and_club_owner_can_approve_without_changing_global_account_status(): void
    {
        [$tenant, $domain, $owner] = $this->createClub('velvet-club.test');
        $applicant = $this->createUser('couple@example.test');

        $this->actingAs($applicant)
            ->get('http://'.$domain.'/membership/apply')
            ->assertOk()
            ->assertSee('Apply to '.$tenant->name)
            ->assertSee('Couple');

        $this->actingAs($applicant)
            ->post('http://'.$domain.'/membership/apply', [
                'profile_type' => 'couple',
                'referred_by' => 'Friends at a Private Gather event',
                'introduction' => 'We are an adult couple looking for a respectful, private community with well-run social events.',
                'rules_ack' => '1',
                'privacy_ack' => '1',
            ])
            ->assertRedirect();

        $application = TenantMembershipApplication::query()->firstOrFail();
        $this->assertSame($tenant->id, $application->tenant_id);
        $this->assertSame($applicant->id, $application->user_id);
        $this->assertSame('couple', $application->profile_type);
        $this->assertSame('pending', $application->status);
        $this->assertTrue((bool) data_get($application->answers, 'rules_acknowledged'));
        $this->assertFalse($tenant->users()->whereKey($applicant->id)->exists());

        $this->actingAs($owner)
            ->get('http://'.$domain.'/manage/membership-applications')
            ->assertOk()
            ->assertSee($applicant->email)
            ->assertSee('Couple application');

        $this->actingAs($owner)
            ->patch('http://'.$domain.'/manage/membership-applications/'.$application->id, [
                'decision' => 'approved',
                'decision_note' => 'Approved after membership review.',
            ])
            ->assertRedirect();

        $application->refresh();
        $this->assertSame('approved', $application->status);
        $this->assertSame($owner->id, $application->reviewed_by);
        $this->assertNotNull($application->reviewed_at);

        $membership = $tenant->users()->whereKey($applicant->id)->firstOrFail()->pivot;
        $this->assertSame('member', $membership->role);
        $this->assertSame('active', $membership->status);
        $this->assertSame('active', $applicant->fresh()->status);
    }

    public function test_manager_cannot_review_an_application_owned_by_another_tenant(): void
    {
        [$tenantA, $domainA, $ownerA] = $this->createClub('first-club.test');
        [$tenantB] = $this->createClub('second-club.test');
        $applicant = $this->createUser('isolation@example.test');

        $application = TenantMembershipApplication::create([
            'tenant_id' => $tenantB->id,
            'user_id' => $applicant->id,
            'profile_type' => 'individual',
            'status' => 'pending',
            'introduction' => 'This application belongs only to the second club and must remain isolated from the first club.',
            'answers' => ['rules_acknowledged' => true, 'privacy_acknowledged' => true],
        ]);

        $this->actingAs($ownerA)
            ->patch('http://'.$domainA.'/manage/membership-applications/'.$application->id, [
                'decision' => 'approved',
            ])
            ->assertNotFound();

        $this->assertSame('pending', $application->fresh()->status);
        $this->assertFalse($tenantA->users()->whereKey($applicant->id)->exists());
        $this->assertFalse($tenantB->users()->whereKey($applicant->id)->exists());
    }

    public function test_non_adult_or_unconfirmed_account_cannot_submit_membership_application(): void
    {
        [, $domain] = $this->createClub('adult-only.test');

        $underage = User::create([
            'name' => 'Underage Test',
            'display_name' => 'Underage Test',
            'email' => 'underage@example.test',
            'password' => 'Password123',
            'date_of_birth' => now()->subYears(17)->toDateString(),
            'status' => 'active',
            'adult_confirmed_at' => null,
            'terms_accepted_at' => now(),
            'privacy_accepted_at' => now(),
            'privacy_version' => '1.0',
        ]);

        $this->actingAs($underage)
            ->post('http://'.$domain.'/membership/apply', [
                'profile_type' => 'individual',
                'introduction' => 'This request should never be accepted because the account is not adult eligible.',
                'rules_ack' => '1',
                'privacy_ack' => '1',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('tenant_membership_applications', 0);
    }

    private function createClub(string $domain): array
    {
        $tenant = Tenant::create([
            'name' => 'Lifestyle '.str_replace('.test', '', $domain),
            'slug' => str_replace('.', '-', $domain),
            'type' => Tenant::TYPE_CLUB,
            'status' => 'active',
            'plan' => 'starter',
            'settings' => [
                'marketplace_enabled' => true,
                'market' => 'adult_lifestyle',
                'adult_only' => true,
            ],
        ]);

        TenantDomain::create([
            'tenant_id' => $tenant->id,
            'domain' => $domain,
            'type' => TenantDomain::TYPE_CUSTOM_DOMAIN,
            'is_primary' => true,
            'status' => TenantDomain::STATUS_ACTIVE,
            'verified_at' => now(),
            'ssl_status' => 'active',
            'dns_status' => 'active',
        ]);

        $owner = $this->createUser('owner-'.str_replace('.', '-', $domain).'@example.test');
        $tenant->users()->attach($owner->id, ['role' => 'owner', 'status' => 'active']);

        return [$tenant, $domain, $owner];
    }

    private function createUser(string $email): User
    {
        return User::create([
            'name' => 'Lifestyle Member',
            'display_name' => 'Lifestyle Member',
            'email' => $email,
            'password' => 'Password123',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'status' => 'active',
            'adult_confirmed_at' => now(),
            'terms_accepted_at' => now(),
            'privacy_accepted_at' => now(),
            'privacy_version' => '1.0',
        ]);
    }
}
