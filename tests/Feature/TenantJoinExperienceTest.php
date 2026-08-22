<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantMembershipApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantJoinExperienceTest extends TestCase
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

    public function test_public_and_signed_in_nonmembers_have_a_clear_join_path(): void
    {
        [$tenant, $domain] = $this->createClub('join-club.test');

        $this->get('http://'.$domain.'/')
            ->assertOk()
            ->assertSee('Apply to Join');

        $applicant = $this->createUser('applicant@example.test');

        $this->actingAs($applicant)
            ->get('http://'.$domain.'/')
            ->assertOk()
            ->assertSee('Apply to Join');

        TenantMembershipApplication::create([
            'tenant_id' => $tenant->id,
            'user_id' => $applicant->id,
            'profile_type' => 'couple',
            'status' => 'pending',
            'introduction' => 'We are applying to become members of this private adult lifestyle community.',
            'answers' => [
                'rules_acknowledged' => true,
                'privacy_acknowledged' => true,
            ],
        ]);

        $this->actingAs($applicant)
            ->get('http://'.$domain.'/')
            ->assertOk()
            ->assertSee('Application Status');
    }

    public function test_active_tenant_member_no_longer_sees_apply_to_join_action(): void
    {
        [$tenant, $domain] = $this->createClub('member-club.test');
        $member = $this->createUser('active-member@example.test');
        $tenant->users()->attach($member->id, ['role' => 'member', 'status' => 'active']);

        $this->actingAs($member)
            ->get('http://'.$domain.'/')
            ->assertOk()
            ->assertDontSee('Apply to Join');
    }

    public function test_operator_dashboard_surfaces_pending_membership_application_count(): void
    {
        [$tenant, $domain] = $this->createClub('operator-club.test');
        $owner = $this->createUser('owner@example.test');
        $applicant = $this->createUser('screening@example.test');

        $tenant->users()->attach($owner->id, ['role' => 'owner', 'status' => 'active']);

        TenantMembershipApplication::create([
            'tenant_id' => $tenant->id,
            'user_id' => $applicant->id,
            'profile_type' => 'individual',
            'status' => 'pending',
            'introduction' => 'I am applying to join this private adult lifestyle club and participate respectfully.',
            'answers' => [
                'rules_acknowledged' => true,
                'privacy_acknowledged' => true,
            ],
        ]);

        $this->actingAs($owner)
            ->get('http://'.$domain.'/manage')
            ->assertOk()
            ->assertSee('Pending Applications')
            ->assertSee('1 pending');
    }

    private function createClub(string $domain): array
    {
        $tenant = Tenant::create([
            'name' => 'Private Lifestyle Club',
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

        return [$tenant, $domain];
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
