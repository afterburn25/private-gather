<?php

namespace Tests\Feature;

use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AccountSecurityIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['platform.central_domains' => ['platform.test']]);
    }

    public function test_two_factor_setup_is_bound_to_user_and_expires_after_ten_minutes(): void
    {
        $user = $this->createUser('setup-expiry@example.test');

        $this->actingAs($user)
            ->withSession([
                '2fa_setup_secret' => 'JBSWY3DPEHPK3PXP',
                '2fa_setup_user' => $user->id,
                '2fa_setup_issued_at' => now()->subMinutes(11)->timestamp,
            ])
            ->post('http://platform.test/security/two-factor/confirm', [
                'code' => '123456',
            ])
            ->assertStatus(422)
            ->assertSessionMissing('2fa_setup_secret')
            ->assertSessionMissing('2fa_setup_user')
            ->assertSessionMissing('2fa_setup_issued_at');

        $this->assertNull($user->fresh()->two_factor_confirmed_at);
    }

    public function test_begin_two_factor_setup_records_user_and_issue_time(): void
    {
        $user = $this->createUser('setup-begin@example.test');

        $response = $this->actingAs($user)
            ->post('http://platform.test/security/two-factor', [
                'password' => 'Password123',
            ]);

        $response->assertOk()
            ->assertSessionHas('2fa_setup_user', $user->id)
            ->assertSessionHas('2fa_setup_issued_at');

        $issuedAt = (int) session('2fa_setup_issued_at');
        $this->assertLessThanOrEqual(5, abs(now()->timestamp - $issuedAt));
        $this->assertNotSame('', (string) session('2fa_setup_secret'));
    }

    public function test_recovery_code_is_one_time_and_consumed_from_encrypted_list(): void
    {
        $user = $this->createUser('recovery-once@example.test');
        $recoveryCode = 'abc123def4';
        $user->forceFill([
            'two_factor_secret' => Crypt::encryptString('JBSWY3DPEHPK3PXP'),
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode([$recoveryCode], JSON_THROW_ON_ERROR)),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->withSession([
            'auth.2fa_user' => $user->id,
            'auth.2fa_remember' => false,
            'auth.2fa_intended' => '/dashboard',
            'auth.2fa_issued_at' => now()->timestamp,
        ])->post('http://platform.test/two-factor-challenge', [
            'code' => $recoveryCode,
        ])->assertRedirect('/dashboard');

        $remaining = json_decode(
            Crypt::decryptString((string) $user->fresh()->two_factor_recovery_codes),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $this->assertSame([], $remaining);
        $this->assertDatabaseHas('security_events', [
            'user_id' => $user->id,
            'event' => '2fa.recovery_used',
        ]);

        $this->post('http://platform.test/logout')->assertRedirect();

        $this->withSession([
            'auth.2fa_user' => $user->id,
            'auth.2fa_remember' => false,
            'auth.2fa_intended' => '/dashboard',
            'auth.2fa_issued_at' => now()->timestamp,
        ])->from('http://platform.test/two-factor-challenge')
            ->post('http://platform.test/two-factor-challenge', [
                'code' => $recoveryCode,
            ])
            ->assertRedirect('http://platform.test/two-factor-challenge')
            ->assertSessionHasErrors('code');
    }

    public function test_disabling_two_factor_requires_enabled_state_and_records_security_event(): void
    {
        $user = $this->createUser('disable-2fa@example.test');
        $user->forceFill([
            'two_factor_secret' => Crypt::encryptString('JBSWY3DPEHPK3PXP'),
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode(['abc123def4'], JSON_THROW_ON_ERROR)),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->actingAs($user)
            ->from('http://platform.test/security')
            ->delete('http://platform.test/security/two-factor', [
                'password' => 'Password123',
            ])
            ->assertRedirect('http://platform.test/security');

        $fresh = $user->fresh();
        $this->assertNull($fresh->two_factor_secret);
        $this->assertNull($fresh->two_factor_recovery_codes);
        $this->assertNull($fresh->two_factor_confirmed_at);
        $this->assertDatabaseHas('security_events', [
            'user_id' => $user->id,
            'event' => '2fa.disabled',
        ]);
    }

    public function test_password_reset_records_security_event_and_revokes_existing_sessions(): void
    {
        $user = $this->createUser('password-security-event@example.test');
        $token = Password::broker()->createToken($user);
        DB::table('sessions')->insert([
            'id' => 'password-reset-security-session',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => 'payload',
            'last_activity' => time(),
        ]);

        $this->post('http://platform.test/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->assertDatabaseHas('security_events', [
            'user_id' => $user->id,
            'event' => 'password.reset',
        ]);
    }

    public function test_password_reset_rejects_non_string_token_payload(): void
    {
        $user = $this->createUser('password-token-shape@example.test');

        $this->from('http://platform.test/reset-password/example')
            ->post('http://platform.test/reset-password', [
                'token' => ['unexpected' => 'array'],
                'email' => $user->email,
                'password' => 'NewPassword123',
                'password_confirmation' => 'NewPassword123',
            ])
            ->assertRedirect('http://platform.test/reset-password/example')
            ->assertSessionHasErrors('token');
    }

    private function createUser(string $email): User
    {
        return User::create([
            'name' => 'Account Security User',
            'display_name' => 'Account Security User',
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
