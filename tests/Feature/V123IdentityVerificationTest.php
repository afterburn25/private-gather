<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Verification;
use App\Services\Verification\MemberTrust;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class V123IdentityVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.url' => 'https://platform.test',
            'edition.name' => 'hosted',
            'platform.root_domain' => 'platform.test',
            'platform.central_domains' => ['platform.test', 'www.platform.test'],
            'platform.tenant_scheme' => 'https',
            'platform.tenant_mount_path' => '',
            'demo.enabled' => false,
            'age_verification.minimum_age' => 21,
        ]);
    }

    public function test_registration_uses_global_username_and_validated_us_state(): void
    {
        $response = $this->post('https://platform.test/register', $this->registrationPayload([
            'username' => 'New.Member',
            'region' => 'Illinois',
        ]));

        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('users', [
            'username' => 'new.member',
            'display_name' => 'new.member',
            'email' => 'new-member@example.test',
        ]);
        $this->assertDatabaseHas('profiles', ['region' => 'Illinois']);
    }

    public function test_username_is_case_normalized_before_global_uniqueness_validation(): void
    {
        $this->user('alice', 'alice@example.test');

        $response = $this->from('/register')->post('https://platform.test/register', $this->registrationPayload([
            'username' => 'ALICE',
            'email' => 'other@example.test',
        ]));

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('username');
    }

    public function test_registration_rejects_non_us_state_value(): void
    {
        $response = $this->from('/register')->post('https://platform.test/register', $this->registrationPayload([
            'region' => 'Atlantis',
        ]));

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('region');
    }

    public function test_first_club_is_allowed_without_verification_but_second_club_is_gated(): void
    {
        $user = $this->user('member-one', 'member-one@example.test');
        $first = $this->club('first-club', 'open');
        $second = $this->club('second-club', 'approval');
        $this->actingAs($user);

        $this->post('https://platform.test/clubs/'.$first->slug.'/apply')
            ->assertSessionHas('status');
        $this->assertDatabaseHas('tenant_users', ['tenant_id' => $first->id, 'user_id' => $user->id, 'status' => 'active']);

        $this->post('https://platform.test/clubs/'.$second->slug.'/apply')
            ->assertRedirect(route('verification.index'));
        $this->assertDatabaseMissing('tenant_users', ['tenant_id' => $second->id, 'user_id' => $user->id]);

        Verification::create([
            'user_id' => $user->id,
            'type' => 'age_identity',
            'status' => 'approved',
            'provider' => 'persona',
            'verified_at' => now(),
            'expires_at' => now()->addMonths(12),
        ]);

        $this->post('https://platform.test/clubs/'.$second->slug.'/apply')
            ->assertSessionHas('status');
        $this->assertDatabaseHas('tenant_users', ['tenant_id' => $second->id, 'user_id' => $user->id, 'status' => 'pending']);
        $this->assertDatabaseHas('membership_applications', ['tenant_id' => $second->id, 'user_id' => $user->id, 'status' => 'pending']);
    }

    public function test_existing_membership_is_not_blocked_by_additional_club_middleware(): void
    {
        $user = $this->user('existing-member', 'existing@example.test');
        $club = $this->club('already-here', 'open');
        $club->users()->attach($user->id, ['role' => 'member', 'status' => 'active']);
        $this->actingAs($user);

        $this->post('https://platform.test/clubs/'.$club->slug.'/apply')
            ->assertSessionHas('status', 'You are already an active member of this club.');
    }

    public function test_linked_couple_requires_both_partners_to_be_verified(): void
    {
        $owner = $this->user('couple-one', 'couple-one@example.test', 'couple');
        $partner = $this->user('couple-two', 'couple-two@example.test', 'couple');
        $owner->profile->update(['partner_user_id' => $partner->id, 'profile_type' => 'couple']);
        $partner->profile->update(['partner_user_id' => $owner->id, 'profile_type' => 'couple']);

        Verification::create([
            'user_id' => $owner->id,
            'type' => 'age_identity',
            'status' => 'approved',
            'provider' => 'persona',
            'verified_at' => now(),
            'expires_at' => now()->addMonths(12),
        ]);

        $trust = app(MemberTrust::class);
        $partial = $trust->badge($owner->fresh('profile'));
        $this->assertFalse($partial['verified']);
        $this->assertSame(1, $partial['verified_count']);
        $this->assertSame(2, $partial['required_count']);

        Verification::create([
            'user_id' => $partner->id,
            'type' => 'age_identity',
            'status' => 'approved',
            'provider' => 'persona',
            'verified_at' => now(),
            'expires_at' => now()->addMonths(12),
        ]);

        $complete = $trust->badge($owner->fresh('profile'));
        $this->assertTrue($complete['verified']);
        $this->assertSame('Verified Member', $complete['label']);
    }

    public function test_verification_member_and_admin_pages_render(): void
    {
        $member = $this->user('verify-me', 'verify-me@example.test');
        $this->actingAs($member)
            ->get('https://platform.test/verification')
            ->assertOk()
            ->assertSee('Age & Identity Verification')
            ->assertSee('Unverified');

        $admin = $this->user('platform-admin', 'admin@example.test');
        $admin->forceFill(['is_platform_admin' => true])->save();
        $this->actingAs($admin)
            ->get('https://platform.test/admin/age-verification')
            ->assertOk()
            ->assertSee('Age & Identity Verification');
    }

    /** @param array<string,mixed> $overrides */
    private function registrationPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Private Legal Name',
            'username' => 'new.member',
            'email' => 'new-member@example.test',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'password' => 'Password12345',
            'password_confirmation' => 'Password12345',
            'lifestyle_identity' => 'single_man',
            'relationship_status' => '',
            'experience_level' => '',
            'pronouns' => '',
            'city' => 'Chicago',
            'region' => 'Illinois',
            'headline' => 'Testing safely',
            'looking_for' => [],
            'lifestyle_interests' => [],
            'boundaries' => '',
            'application_note' => '',
            'adult' => '1',
            'terms' => '1',
            'privacy' => '1',
            'consent_culture' => '1',
        ], $overrides);
    }

    private function user(string $username, string $email, string $profileType = 'individual'): User
    {
        $user = User::create([
            'name' => 'Private Legal Name',
            'username' => $username,
            'display_name' => $username,
            'email' => $email,
            'password' => 'Password12345',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'status' => 'active',
            'is_platform_admin' => false,
            'adult_confirmed_at' => now(),
            'terms_accepted_at' => now(),
            'privacy_accepted_at' => now(),
            'privacy_version' => '1.0',
        ]);
        Profile::create([
            'user_id' => $user->id,
            'profile_type' => $profileType,
            'lifestyle_identity' => $profileType === 'couple' ? 'couple' : 'single_man',
            'visibility' => ['profile' => 'members'],
            'discoverable' => true,
            'message_permissions' => 'members',
        ]);
        return $user->fresh('profile');
    }

    private function club(string $slug, string $registration): Tenant
    {
        return Tenant::create([
            'name' => ucwords(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'type' => Tenant::TYPE_CLUB,
            'status' => 'active',
            'plan' => 'starter',
            'settings' => ['membership_registration' => $registration],
        ]);
    }
}
