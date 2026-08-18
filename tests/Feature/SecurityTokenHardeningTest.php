<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SecurityTokenHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_staff_invitation_stores_only_a_digest_and_raw_link_still_accepts(): void
    {
        [$tenant, $domain] = $this->createTenant('invite-hash.test');
        $owner = $this->createUser('owner-hash@example.test');
        $invitee = $this->createUser('invitee-hash@example.test');
        $tenant->users()->attach($owner->id, ['role' => 'owner', 'status' => 'active']);

        $response = $this->actingAs($owner)
            ->post('http://'.$domain.'/manage/staff/invite', [
                'email' => $invitee->email,
                'role' => 'staff',
            ]);

        $response->assertRedirect('http://'.$domain.'/manage/staff');
        $status = (string) session('status');
        $this->assertMatchesRegularExpression('#/staff-invite/([a-f0-9]{64})#', $status);
        preg_match('#/staff-invite/([a-f0-9]{64})#', $status, $matches);
        $rawToken = $matches[1];

        $stored = (string) DB::table('tenant_invitations')
            ->where('tenant_id', $tenant->id)
            ->where('email', $invitee->email)
            ->value('token');

        $this->assertNotSame($rawToken, $stored);
        $this->assertSame(hash('sha256', $rawToken), $stored);

        $this->actingAs($invitee)
            ->get('/staff-invite/'.$rawToken)
            ->assertRedirect('https://'.$domain.'/manage');

        $this->assertDatabaseHas('tenant_users', [
            'tenant_id' => $tenant->id,
            'user_id' => $invitee->id,
            'role' => 'staff',
            'status' => 'active',
        ]);
    }

    public function test_pre_hardening_plaintext_staff_invitation_remains_usable(): void
    {
        [$tenant, $domain] = $this->createTenant('legacy-invite.test');
        $invitee = $this->createUser('legacy-invitee@example.test');
        $rawToken = bin2hex(random_bytes(32));

        DB::table('tenant_invitations')->insert([
            'tenant_id' => $tenant->id,
            'email' => $invitee->email,
            'role' => 'manager',
            'token' => $rawToken,
            'expires_at' => now()->addHour(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($invitee)
            ->get('/staff-invite/'.$rawToken)
            ->assertRedirect('https://'.$domain.'/manage');

        $this->assertDatabaseHas('tenant_users', [
            'tenant_id' => $tenant->id,
            'user_id' => $invitee->id,
            'role' => 'manager',
            'status' => 'active',
        ]);
    }

    public function test_stale_two_factor_challenge_is_rejected_and_cleared(): void
    {
        $user = $this->createUser('stale-2fa@example.test');

        $this->withSession([
            'auth.2fa_user' => $user->id,
            'auth.2fa_remember' => true,
            'auth.2fa_intended' => '/dashboard',
            'auth.2fa_issued_at' => now()->subMinutes(11)->timestamp,
        ])->get('/two-factor-challenge')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email')
            ->assertSessionMissing('auth.2fa_user')
            ->assertSessionMissing('auth.2fa_issued_at');
    }

    public function test_fresh_two_factor_challenge_is_available_for_ten_minutes(): void
    {
        $user = $this->createUser('fresh-2fa@example.test');

        $this->withSession([
            'auth.2fa_user' => $user->id,
            'auth.2fa_issued_at' => now()->subMinutes(9)->timestamp,
        ])->get('/two-factor-challenge')
            ->assertOk();
    }

    private function createUser(string $email): User
    {
        return User::create([
            'name' => 'Test User',
            'display_name' => 'Test User',
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

    private function createTenant(string $domain): array
    {
        $tenant = Tenant::create([
            'name' => 'Token Test Organization',
            'slug' => str_replace('.', '-', $domain),
            'type' => 'club',
            'status' => 'active',
            'plan' => 'starter',
            'settings' => ['marketplace_enabled' => true],
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
}
